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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        // $image = Image::make($uploadedFile->getRealPath());

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
                $encodedImage = $image->toPng()->toString(); // ->toPng(['compression' => 6])
                break;
            case 'webp':
                $encodedImage = $image->toWebp(self::PROFILE_IMAGE_QUALITY)->toString();
                break;
            default:
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
            'accessor_username' => Auth::user()->username,
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

            try {
                $postsHtml = view('users.partials.posts-list', compact('posts', 'showManagementOptions'))->render();
                Log::info('View rendered successfully');
            } catch (Exception $e) {
                Log::error('View rendering failed: ' . $e->getMessage());
                return response()->json([
                    'html' => '<p>Error rendering view: ' . $e->getMessage() . '</p>',
                    'hasMorePages' => false
                ], 500);
            }

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
        return view('users.edit', compact('user'));
    }

    final public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
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
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $oldValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'profile_picture_removed' => $request->boolean('remove_profile_picture'),
            'profile_picture_updated' => $request->hasFile('profile_picture'),
            'show_voted_posts_publicly' => $user->show_voted_posts_publicly,
        ];

        $data = $request->only(['first_name', 'last_name', 'username']);
        $data['show_voted_posts_publicly'] = $request->boolean('show_voted_posts_publicly');

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
        } else if ($request->boolean('remove_profile_picture') ||
            (($request->first_name !== $user->first_name || $request->last_name !== $user->last_name) &&
                $user->profile_picture && str_contains($user->profile_picture, 'initial_'))) {
            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
            }
            $data['profile_picture'] = $this->avatarService->generateInitialsAvatar(
                $request->first_name, $request->last_name ?? '', $user->id
            );
        }

        $user->update($data);
        Log::channel('audit_trail')->info('User profile updated.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'old_username' => $oldValues['username'] !== $user->username ? $oldValues['username'] : null,
            'updated_fields' => array_keys($data),
            // 'changes' => [ 'field' => ['old' => ..., 'new' => ...], ... ]
            'profile_picture_change_details' => [
                'removed' => $oldValues['profile_picture_removed'],
                'uploaded_new' => $oldValues['profile_picture_updated'],
                'path_after_update' => $user->profile_picture,
            ],
            'privacy_setting_show_voted_changed_to' => $data['show_voted_posts_publicly'],
            'ip_address' => $request->ip(),
        ]);
        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    final public function showChangePasswordForm(): View
    {
        $user = Auth::user();
        if (!Auth::user()->password && Auth::user()->google_id) {
            return redirect()->route('profile.edit')->with('info', 'Password change is not available for accounts created via Google login unless a password has been set manually.');
        }
        Log::channel('audit_trail')->info('User password changed.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip_address' => $request->ip(),
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
                    $fail('The :attribute is incorrect.');
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

        return redirect()->route('password.change.form')->with('success', 'Password changed successfully.');
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
            return response()->json(['available' => false]);
        }

        $query = User::where('username', $username);

        if (auth()->check()) {
            $query->where('id', '!=', auth()->id());
        }

        $exists = $query->exists();

        return response()->json([
            'available' => !$exists
        ]);
    }
}
