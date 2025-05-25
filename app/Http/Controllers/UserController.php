<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vote;
use App\Services\AvatarService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;


class UserController extends Controller
{
    protected AvatarService $avatarService;
    private const PROFILE_IMAGE_SIZE = 300;
    private const PROFILE_IMAGE_QUALITY = 75;

    public function __construct(AvatarService $avatarService)
    {
        $this->avatarService = $avatarService;
    }

    private function processAndStoreProfileImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): string
    {
        $manager = ImageManager::gd();
        $image = $manager->read($uploadedFile->getRealPath());

        $image->cover(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);

        $originalExtension = $uploadedFile->getClientOriginalExtension();
        $extension = strtolower($originalExtension);

        $allowedExtensions = ['jpeg', 'jpg', 'png', 'webp'];
        $outputExtension = in_array($extension, $allowedExtensions) ? $extension : 'jpg';

        if ($extension === 'gif') {
            $outputExtension = 'jpg';
        }

        $filename = $baseFilename . '.' . $outputExtension;
        $path = $directory . '/' . $filename;

        switch ($outputExtension) {
            case 'jpeg':
            case 'jpg':
                $encodedImage = $image->toJpeg(self::PROFILE_IMAGE_QUALITY)->toString();
                break;
            case 'png':
                $encodedImage = $image->toPng()->toString();
                break;
            case 'webp':
                $encodedImage = $image->toWebp(self::PROFILE_IMAGE_QUALITY)->toString();
                break;
            default: // Should not happen due to previous logic, but as a fallback
                $filename = $baseFilename . '.jpg';
                $path = $directory . '/' . $filename;
                $encodedImage = $image->toJpeg(self::PROFILE_IMAGE_QUALITY)->toString();
        }

        Storage::disk('public')->put($path, $encodedImage);
        return $path;
    }


    final public function showProfile(string $username): View
    {
        $user = User::where('username', $username)
            ->withCount('posts')
            ->firstOrFail();

        $isOwnProfile = Auth::check() && Auth::id() === $user->id;
        $totalVotesOnUserPosts = $user->posts()->sum('total_votes');
        return view('users.profile', compact('user', 'isOwnProfile', 'totalVotesOnUserPosts'));
    }

    final public function getUserPosts(Request $request, string $username): JsonResponse
    {
        Log::channel('audit_trail')->info('User posts data accessed.', [
            'accessor_user_id' => Auth::id(),
            'accessor_username' => Auth::user()?->username,
            'profile_username_viewed' => $username,
            'ip_address' => $request->ip(),
            'page' => $request->input('page', 1)
        ]);

        try {
            Log::info('getUserPosts called', [
                'username' => $username,
                'page' => $request->input('page')
            ]);

            $user = User::where('username', $username)->firstOrFail();
            Log::info('User found', ['user_id' => $user->id]);

            $posts = $user->posts()
                ->withPostData()
                ->latest()
                ->paginate(10);

            Log::info('Posts retrieved', ['count' => $posts->count()]);

            if ($posts->count() > 0) {
                $this->attachUserVoteStatus($posts);
            }

            $showManagementOptions = Auth::check() && Auth::id() === $user->id;

            $postsHtml = view('users.partials.posts-list', compact('posts', 'showManagementOptions'))->render();
            Log::info('View rendered successfully');


            return response()->json([
                'html' => $postsHtml,
                'hasMorePages' => $posts->hasMorePages()
            ]);
        } catch (Exception $e) {
            Log::error('Error loading user posts: ' . $e->getMessage(), [
                'user' => $username,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'html' => '<p>Error loading posts: ' . $e->getMessage() . '</p>',
                'hasMorePages' => false
            ], 500);
        }
    }

    final public function getUserVotedPosts(Request $request, string $username): JsonResponse
    {
        try {
            Log::info('getUserVotedPosts called', [
                'username' => $username,
                'page' => $request->input('page')
            ]);

            $profileUser = User::where('username', $username)->firstOrFail();
            $isOwnProfile = Auth::check() && Auth::id() === $profileUser->id;

            if (!$isOwnProfile && !$profileUser->show_voted_posts_publicly) {
                Log::info('Access to voted posts denied due to privacy settings.', ['profile_user_id' => $profileUser->id]);
                return response()->json([
                    'html' => '<p class="text-center text-gray-500 py-8">This user has chosen to keep their voted posts private.</p>',
                    'hasMorePages' => false
                ], 403);
            }

            Log::info('User authorized to view voted posts.', ['profile_user_id' => $profileUser->id, 'viewer_is_owner' => $isOwnProfile]);

            $posts = $profileUser->votedPosts()
                ->withPostData()
                ->latest('votes.created_at')
                ->paginate(10);

            Log::info('Voted posts retrieved', ['count' => $posts->count()]);

            if ($posts->count() > 0) {
                $this->attachUserVoteStatus($posts);
            }

            $showManagementOptions = $isOwnProfile;
            $profileOwnerToDisplay = $profileUser;
            $postsHtml = view('users.partials.posts-list', compact(
                'posts',
                'showManagementOptions',
                'profileOwnerToDisplay'
            ))->render();


            return response()->json([
                'html' => $postsHtml,
                'hasMorePages' => $posts->hasMorePages()
            ]);

        } catch (Exception $e) {
            Log::error('Error loading user voted posts: ' . $e->getMessage(), [
                'user' => $username,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'html' => '<p class="text-red-500 text-center py-8">Error loading voted posts. Please try again.</p>',
                'hasMorePages' => false
            ], 500);
        }
    }


    final public function edit(): View
    {
        $user = Auth::user();
        // $availableLocales = Config::get('app.available_locales', ['en' => 'English']);
        return view('users.edit', compact('user'));
    }

    final public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $availableLocaleKeys = array_keys(Config::get('app.available_locales', ['en' => 'English']));

        Log::info('--------------------------------------------------');
        Log::info('UserController@update: Process started.');
        Log::info('UserController@update: Authenticated User ID: ' . $user->id . ', Username: ' . $user->username);
        Log::info('UserController@update: Current user locale (before update): ' . $user->locale);
        Log::info('UserController@update: All request data: ', $request->all());
        Log::info('UserController@update: Request has "locale" field: ' . ($request->has('locale') ? 'Yes' : 'No'));
        Log::info('UserController@update: Request "locale" value: ' . $request->input('locale'));
        Log::info('UserController@update: Request "locale" is filled: ' . ($request->filled('locale') ? 'Yes' : 'No'));


        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => [
                'required', 'string', 'min:5', 'max:24', 'alpha_dash',
                Rule::unique('users')->ignore($user->id),
                'regex:/^[a-zA-Z][a-zA-Z0-9_-]*$/', 'not_regex:/^\d+$/', 'not_regex:/(.)\1{2,}/',
            ],
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_profile_picture' => 'nullable|boolean',
            'show_voted_posts_publicly' => 'required|boolean',
            'locale' => ['nullable', Rule::in($availableLocaleKeys)],
        ]);

        if ($validator->fails()) {
            Log::warning('UserController@update: Validation failed.', $validator->errors()->toArray());
            return redirect()->back()->withErrors($validator)->withInput();
        }
        Log::info('UserController@update: Validation passed.');

        $oldValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'locale' => $user->locale,
            'profile_picture_removed' => $request->boolean('remove_profile_picture'),
            'profile_picture_updated' => $request->hasFile('profile_picture'),
            'show_voted_posts_publicly' => $user->show_voted_posts_publicly,
        ];

        $data = $request->only(['first_name', 'last_name', 'username']);
        $data['show_voted_posts_publicly'] = $request->boolean('show_voted_posts_publicly');

        if ($request->has('locale')) {
            $requestedLocale = $request->input('locale');
            Log::info('UserController@update: "locale" key IS PRESENT in request. Value: "' . $requestedLocale . '"');
            if (in_array($requestedLocale, $availableLocaleKeys)) {
                $data['locale'] = $requestedLocale;
                Log::info('UserController@update: Valid locale "' . $requestedLocale . '" will be set in \$data.');
            } elseif ($requestedLocale === null || $requestedLocale === '') {
                $data['locale'] = null;
                Log::info('UserController@update: Empty/null locale received, will set user locale to NULL in \$data.');
            } else {
                Log::warning('UserController@update: Invalid locale "' . $requestedLocale . '" received but not caught by validator (or not intended to be set). Not adding to \$data.');
            }
        } else {
            Log::info('UserController@update: "locale" key IS NOT PRESENT in request. User locale will not be changed by \$data array.');
        }


        $nameChanged = ($request->first_name !== $user->first_name || $request->last_name !== $user->last_name);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
            }
            $data['profile_picture'] = $this->processAndStoreProfileImage(
                $request->file('profile_picture'),
                'profile_pictures',
                uniqid('profile_') . '_' . $user->id
            );
        } else {
            $shouldGenerateInitials = false;

            if ($request->boolean('remove_profile_picture')) {
                $shouldGenerateInitials = true;
            } elseif ($nameChanged) {
                if (!$user->profile_picture || ($user->profile_picture && str_contains($user->profile_picture, 'initial_'))) {
                    $shouldGenerateInitials = true;
                }
            }

            if ($shouldGenerateInitials) {
                if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL)) {
                    if (Storage::disk('public')->exists($user->profile_picture)) {
                        Storage::disk('public')->delete($user->profile_picture);
                    }
                }
                $data['profile_picture'] = $this->avatarService->generateInitialsAvatar(
                    $request->first_name,
                    $request->last_name ?? '',
                    $user->id
                );
            }
        }

        Log::info('UserController@update: Data array just before user->update(): ', $data);

        try {
            $updateResult = $user->update($data);
            Log::info('UserController@update: User update operation result: ' . ($updateResult ? 'Success' : 'Failure'));
            if ($updateResult) {
                Log::info('UserController@update: User changes detected by Eloquent: ', $user->getChanges());
            }
        } catch (Exception $e) {
            Log::error('UserController@update: Exception during user->update(): ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'An error occurred while updating the profile.')->withInput();
        }


        $freshUser = $user->fresh();
        if ($freshUser) {
            Log::info('UserController@update: User locale from DB after update (fresh instance): "' . $freshUser->locale . '"');
        } else {
            Log::error('UserController@update: Could not get fresh user instance from DB.');
        }


        if (array_key_exists('locale', $data)) {
            if ($oldValues['locale'] !== $data['locale']) {
                if ($data['locale'] !== null) {
                    Session::put('locale', $data['locale']);
                    Log::info('UserController@update: Session locale set to: "' . $data['locale'] . '"');
                } else {
                    Session::forget('locale');
                    Log::info('UserController@update: Session locale forgotten (new locale is null).');
                }
                Session::save();
                Log::info('UserController@update: Session::save() called.');
            }
        }


        Log::info('UserController@update: Process finished. Redirecting...');
        Log::info('--------------------------------------------------');

        return redirect()->route('profile.edit')->with('success', __('messages.profile_updated_success'));
    }

    final public function showChangePasswordForm(): View
    {
        $user = Auth::user();
        if (!$user->password && $user->google_id) {
            return redirect()->route('profile.edit')->with('info', __('messages.info_password_change_not_available_google'));
        }
        Log::channel('audit_trail')->info('User accessed change password form.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip_address' => request()->ip(),
        ]);
        return view('users.change-password');
    }

    final public function changePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->password) {
            return redirect()->back()->with('error', 'You cannot change password as none is set (likely logged in via social provider). Consider setting one first if needed.');
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail(__('validation.current_password'));
                }
            }],
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        Log::channel('audit_trail')->info('User password changed successfully.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('password.change.form')->with('success', __('messages.password_changed_successfully'));
    }

    private function attachUserVoteStatus(LengthAwarePaginator $posts): void
    {
        $userVoteMap = collect();
        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id')->all();

            if (!empty($postIds)) {
                $userVoteMap = Vote::where('user_id', $loggedInUserId)
                    ->whereIn('post_id', $postIds)
                    ->pluck('vote_option', 'post_id');
            }
        }

        $posts->getCollection()->transform(function ($post) use ($userVoteMap) {
            $post->user_vote = $userVoteMap->get($post->id);
            return $post;
        });
    }

    final public function checkUsername(Request $request): JsonResponse
    {
        $username = $request->input('username');

        if (empty($username)) {
            return response()->json(['available' => false, 'message' => '']);
        }

        $query = User::where('username', $username);

        if (auth()->check()) {
            $query->where('id', '!=', auth()->id());
        }

        $exists = $query->exists();
        $messageKey = $exists ? 'messages.username_taken' : 'messages.username_available';

        return response()->json([
            'available' => !$exists,
            'message' => __($messageKey)
        ]);
    }
}
