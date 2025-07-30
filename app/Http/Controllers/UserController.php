<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vote;
use App\Services\AvatarService;
use App\Services\ImageGenerationService;
use App\Services\ModerationService;
use App\Services\RatingService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as FacadeRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Jenssegers\Agent\Agent;

class UserController extends Controller
{
    private const PROFILE_IMAGE_SIZE = 300;
    private const PROFILE_IMAGE_QUALITY = 75;
    private const HEADER_IMAGE_WIDTH = 1500;
    private const HEADER_IMAGE_QUALITY = 80;
    protected AvatarService $avatarService;
    protected ModerationService $moderationService;
    protected RatingService $ratingService;

    protected ImageGenerationService $imageGenerationService;

    public function __construct(
        AvatarService     $avatarService,
        ModerationService $moderationService,
        RatingService     $ratingService,
        ImageGenerationService $imageGenerationService
    )
    {
        $this->avatarService = $avatarService;
        $this->moderationService = $moderationService;
        $this->ratingService = $ratingService;
        $this->imageGenerationService = $imageGenerationService;
    }

    final public function showProfile(string $username): View
    {
        $user = User::where('username', $username)
            ->withCount('posts')
            ->firstOrFail();

        $headerBackgroundUrl = null;
        if ($user->header_background) {
            if (Str::startsWith($user->header_background, 'template_')) {
                $headerBackgroundUrl = $this->getHeaderTemplates()[$user->header_background] ?? null;
            } else {
                $headerBackgroundUrl = Storage::url($user->header_background);
            }
        }

        $isOwnProfile = Auth::check() && Auth::id() === $user->id;
        $totalVotesOnUserPosts = $user->posts()->sum('total_votes');

        $userBadges = $this->ratingService->getUserBadges($user);

        return view('users.profile', compact(
            'user',
            'isOwnProfile',
            'totalVotesOnUserPosts',
            'headerBackgroundUrl',
            'userBadges'
        ));
    }

    private function getHeaderTemplates(): array
    {
        return [
            'template_1.jpg' => asset('images/header-templates/template_1.jpg'),
            'template_2.jpg' => asset('images/header-templates/template_2.jpg'),
            'template_3.jpg' => asset('images/header-templates/template_3.jpg'),
            'template_4.jpg' => asset('images/header-templates/template_4.jpg'),
            'template_5.jpg' => asset('images/header-templates/template_5.jpg'),
            'template_6.jpg' => asset('images/header-templates/template_6.jpg'),
            'template_7.jpg' => asset('images/header-templates/template_7.jpg'),
            'template_8.jpg' => asset('images/header-templates/template_8.jpg'),
            'template_9.jpg' => asset('images/header-templates/template_9.jpg'),
            'template_10.jpg' => asset('images/header-templates/template_10.jpg'),
            'template_11.jpg' => asset('images/header-templates/template_11.jpg'),
            'template_12.jpg' => asset('images/header-templates/template_12.jpg'),
        ];
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

    final public function terminateSession(string $sessionId): RedirectResponse
    {
        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', $sessionId)
            ->delete();

        Log::channel('audit_trail')->info('User terminated a session.', [
            'user_id' => Auth::id(),
            'username' => Auth::user()->username,
            'terminated_session_id' => $sessionId,
            'ip_address' => FacadeRequest::ip(),
        ]);

        return redirect()->route('profile.edit')->with('success', __('messages.session_terminated_successfully'));
    }

    final public function terminateAllOtherSessions(): RedirectResponse
    {
        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', Session::getId())
            ->delete();

        Log::channel('audit_trail')->info('User terminated all other sessions.', [
            'user_id' => Auth::id(),
            'username' => Auth::user()->username,
            'current_session_id' => Session::getId(),
            'ip_address' => FacadeRequest::ip(),
        ]);

        return redirect()->route('profile.edit')->with('success', __('messages.all_other_sessions_terminated'));
    }

    final public function edit(): View
    {
        $user = Auth::user();
        $available_locales = Config::get('app.available_locales', ['en' => 'English']);
        $current_locale = Session::get('locale', Config::get('app.locale'));
        $headerTemplates = $this->getHeaderTemplates();

        $authMethods = [
            'password' => !is_null($user->password),
            'google' => !is_null($user->google_id),
            'x' => !is_null($user->x_id),
            'telegram' => !is_null($user->telegram_id),
        ];
        $authMethodsCount = $user->getActiveAuthMethodsCount();

        $sessions = collect();
        if (config('session.driver') === 'database') {
            $sessionsCollection = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderBy('last_activity', 'desc')
                ->get();

            $agent = new Agent();
            $currentSessionId = Session::getId();

            $sessions = $sessionsCollection->map(function ($session) use ($agent, $currentSessionId) {
                if (empty($session->user_agent)) {
                    return null;
                }
                $agent->setUserAgent($session->user_agent);

                $location = $session->ip_address ? 'IP: ' . $session->ip_address : __('messages.unknown_location');

                return (object)[
                    'id' => $session->id,
                    'agent' => (object)[
                        'browser' => $agent->browser() ?: __('messages.unknown_browser'),
                        'platform' => $agent->platform() ?: __('messages.unknown_platform'),
                    ],
                    'ip_address' => $session->ip_address,
                    'location' => $location,
                    'is_current_device' => $session->id === $currentSessionId,
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            })->filter();
        }

        return view('users.edit', compact(
            'user',
            'available_locales',
            'current_locale',
            'headerTemplates',
            'sessions',
            'authMethods',
            'authMethodsCount'
        ));
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
            return redirect()->back()->with('error', __('messages.error_password_change_not_set'));
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

    final public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $availableLocaleKeys = array_keys(Config::get('app.available_locales', ['en' => 'English']));
        $moderationLanguageCode = $user->locale ?? Config::get('app.locale');
        $headerTemplateKeys = array_keys($this->getHeaderTemplates());

        Log::info('--------------------------------------------------');
        Log::info('UserController@update: Process started.');
        Log::info('UserController@update: Authenticated User ID: ' . $user->id . ', Username: ' . $user->username);
        Log::info('UserController@update: Current user locale (before update): ' . $user->locale);
        Log::info('UserController@update: All request data: ', $request->all());
        Log::info('UserController@update: Request has "locale" field: ' . ($request->has('locale') ? 'Yes' : 'No'));
        Log::info('UserController@update: Request "locale" value: ' . $request->input('locale'));
        Log::info('UserController@update: Request "locale" is filled: ' . ($request->filled('locale') ? 'Yes' : 'No'));


        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => [
                'required', 'string', 'min:5', 'max:24', 'alpha_dash',
                Rule::unique('users')->ignore($user->id),
                'regex:/^[a-zA-Z][a-zA-Z0-9_-]*$/', 'not_regex:/^\d+$/', 'not_regex:/(.)\1{3,}/',
            ],
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_profile_picture' => 'nullable|boolean',
            'header_background_upload' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'header_background_template' => ['nullable', 'string', Rule::in($headerTemplateKeys)],
            'remove_header_background' => 'nullable|boolean',
            'show_voted_posts_publicly' => 'required|boolean',
            'receives_notifications' => 'required|boolean',
            'ai_insight_preference' => ['required', 'string', Rule::in(['expanded', 'less', 'hidden'])],
            'locale' => ['nullable', Rule::in($availableLocaleKeys)],
            'external_links' => 'nullable|array|max:3',
            'external_links.*' => ['nullable', 'url', 'max:255', function ($attribute, $value, $fail) {
                if (!empty($value) && !Str::startsWith($value, ['http://', 'https://'])) {
                    $fail(__('messages.external_link_invalid_url_error'));
                }
            }],
        ];

        $customMessages = [
            'external_links.max' => __('messages.external_links_max_error'),
            'external_links.*.url' => __('messages.external_link_invalid_url_error'),
            'external_links.*.max' => __('messages.external_link_too_long_error'),
            'external_links.*.moderation' => __('messages.external_link_inappropriate'),
            'first_name.moderation' => __('messages.content_inappropriate', ['field' => __('messages.first_name_label')]),
            'last_name.moderation' => __('messages.content_inappropriate', ['field' => __('messages.last_name_label')]),
            'username.moderation' => __('messages.content_inappropriate', ['field' => __('messages.username_label')]),
            'profile_picture.moderation' => __('messages.image_inappropriate'),
        ];

        $validator = Validator::make($request->all(), $rules, $customMessages);

        if ($validator->fails()) {
            Log::warning('UserController@update: Validation failed.', $validator->errors()->toArray());
            return redirect()->back()->withErrors($validator)->withInput();
        }
        Log::info('UserController@update: Validation passed.');

        $moderationPassed = true;

        if ($request->filled('first_name')) {
            $firstNameModeration = $this->moderationService->moderateText($request->input('first_name'), $moderationLanguageCode);
            if (!$firstNameModeration['is_appropriate']) {
                $validator->errors()->add('first_name', $firstNameModeration['reason'] ?: $customMessages['first_name.moderation']);
                $moderationPassed = false;
            }
        }

        // 2. Moderate Last Name
        if ($request->filled('last_name')) {
            $lastNameModeration = $this->moderationService->moderateText($request->input('last_name'), $moderationLanguageCode);
            if (!$lastNameModeration['is_appropriate']) {
                $validator->errors()->add('last_name', $lastNameModeration['reason'] ?: $customMessages['last_name.moderation']);
                $moderationPassed = false;
            }
        }

        // 3. Moderate Username (only if changed)
        if ($request->input('username') !== $user->username && $request->filled('username')) {
            $usernameModeration = $this->moderationService->moderateText($request->input('username'), $moderationLanguageCode);
            if (!$usernameModeration['is_appropriate']) {
                $validator->errors()->add('username', $usernameModeration['reason'] ?: $customMessages['username.moderation']);
                $moderationPassed = false;
            }
        }

        // 4. Moderate Profile Picture
        if ($request->hasFile('profile_picture')) {
            $imageModeration = $this->moderationService->moderateImage($request->file('profile_picture'), $moderationLanguageCode);
            if (!$imageModeration['is_appropriate']) {
                $validator->errors()->add('profile_picture', $imageModeration['reason'] ?: $customMessages['profile_picture.moderation']);
                $moderationPassed = false;
            }
        }
        // 5 Moderate External Links
        $externalLinksInput = $request->input('external_links', []);
        $linksForStorage = [];

        if (!empty($externalLinksInput) && is_array($externalLinksInput)) {
            foreach ($externalLinksInput as $index => $linkValue) {
                if (empty($linkValue)) {
                    continue;
                }

                if ($validator->errors()->has("external_links.{$index}")) {
                    $moderationPassed = false;
                    continue;
                }

                $sanitizedLink = filter_var(trim($linkValue), FILTER_SANITIZE_URL);

                if (!(filter_var($sanitizedLink, FILTER_VALIDATE_URL) && Str::startsWith($sanitizedLink, ['http://', 'https://']))) {
                    $validator->errors()->add("external_links.{$index}", __('messages.external_link_invalid_url_error'));
                    $moderationPassed = false;
                    Log::warning('UserController@update: Link became invalid after sanitization right before moderation API call.', ['original_link' => $linkValue, 'sanitized' => $sanitizedLink, 'index' => $index]);
                    continue;
                }

                Log::info("UserController@update: Moderating external link [{$index}]: {$sanitizedLink}");
                $linkModeration = $this->moderationService->moderateUrl($sanitizedLink, $moderationLanguageCode);

                if (!$linkModeration['is_appropriate']) {
                    $errorMessage = $linkModeration['reason'] ?: __('messages.external_link_inappropriate');
                    if (in_array($linkModeration['category'], ['ERROR', 'EXCEPTION'])) {
                        $errorMessage = __('messages.external_link_moderation_failed');
                    } elseif ($linkModeration['reason']) {
                        $errorMessage = $linkModeration['reason'];
                    } elseif ($linkModeration['category'] !== 'NONE') {
                        $errorMessage = __('messages.external_link_flagged_unsafe', ['category' => $linkModeration['category']]);
                    }

                    $validator->errors()->add("external_links.{$index}", $errorMessage);
                    $moderationPassed = false;
                    Log::warning('UserController@update: External link moderation failed by Gemini.', [
                        'link' => $sanitizedLink, 'index' => $index, 'reason' => $linkModeration['reason'], 'category' => $linkModeration['category']
                    ]);
                } else {
                    $linksForStorage[] = $sanitizedLink;
                }
            }
        }

        if (!$moderationPassed || $validator->fails()) {
            Log::warning('UserController@update: Overall moderation or validation checks failed.', $validator->errors()->toArray());
            return redirect()->back()->withErrors($validator)->withInput();
        }
        Log::info('UserController@update: All moderation and validation checks passed.');

        $oldValues = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'locale' => $user->locale,
            'profile_picture_removed' => $request->boolean('remove_profile_picture'),
            'profile_picture_updated' => $request->hasFile('profile_picture'),
            'show_voted_posts_publicly' => $user->show_voted_posts_publicly,
            'external_links' => $user->external_links,
        ];

        // --- START HEADER BACKGROUND LOGIC ---
        $headerData = [];
        $oldHeader = $user->header_background;
        $isOldHeaderCustom = $oldHeader && !Str::startsWith($oldHeader, 'template_');

        if ($request->boolean('remove_header_background')) {
            if ($isOldHeaderCustom && Storage::disk('public')->exists($oldHeader)) {
                Storage::disk('public')->delete($oldHeader);
            }
            $headerData['header_background'] = null;
        } elseif ($request->hasFile('header_background_upload')) {
            if ($isOldHeaderCustom && Storage::disk('public')->exists($oldHeader)) {
                Storage::disk('public')->delete($oldHeader);
            }
            $headerData['header_background'] = $this->processAndStoreHeaderImage(
                $request->file('header_background_upload'), 'header_backgrounds', 'user_' . $user->id . '_' . time()
            );
        } elseif ($request->filled('header_background_template') && in_array($request->input('header_background_template'), $headerTemplateKeys)) {
            if ($isOldHeaderCustom && Storage::disk('public')->exists($oldHeader)) {
                Storage::disk('public')->delete($oldHeader);
            }
            $headerData['header_background'] = $request->input('header_background_template');
        }


        $data = $request->only(['first_name', 'last_name', 'username']);
        $data['show_voted_posts_publicly'] = $request->boolean('show_voted_posts_publicly');
        $data['receives_notifications'] = $request->boolean('receives_notifications');
        $data['ai_insight_preference'] = $request->input('ai_insight_preference');

        if (!empty($headerData) || $request->boolean('remove_header_background')) {
            $data = array_merge($data, $headerData);
        }

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
                Str::slug($data['username'] ?: $data['first_name']) . '_' . $user->id . '_' . time()
            );
        } elseif ($request->boolean('remove_profile_picture')) {
            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
            }
            $data['profile_picture'] = $this->avatarService->generateInitialsAvatar(
                $data['first_name'],
                $data['last_name'] ?? '',
                $user->id
            );
        } elseif ($nameChanged && (!$user->profile_picture || str_contains($user->profile_picture, 'initial_'))) {
            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            $data['profile_picture'] = $this->avatarService->generateInitialsAvatar(
                $data['first_name'],
                $data['last_name'] ?? '',
                $user->id
            );
        }

        $externalLinksInput = $request->input('external_links', []);
        $processedExternalLinks = [];
        foreach ($externalLinksInput as $link) {
            if (!empty($link)) {
                $sanitizedLink = filter_var(trim($link), FILTER_SANITIZE_URL);
                if (filter_var($sanitizedLink, FILTER_VALIDATE_URL) && Str::startsWith($sanitizedLink, ['http://', 'https://'])) {
                    $processedExternalLinks[] = $sanitizedLink;
                } else {
                    Log::warning('UserController@update: Discarded invalid external link after sanitization.', ['link' => $link, 'sanitized' => $sanitizedLink]);
                }
            }
        }
        $data['external_links'] = array_slice($linksForStorage, 0, 3);

        Log::info('UserController@update: Data array just before user->update(): ', $data);
        $updated = false;

        try {
            $updated = $user->update($data);
            if ($updated) {
                Log::info('UserController@update: User profile updated successfully.', ['user_id' => $user->id, 'changes' => $user->getChanges()]);
            } else {
                $dirtyAttributes = $user->getDirty();
                if (empty($dirtyAttributes) && empty(array_diff_assoc($data['external_links'] ?? [], $oldValues['external_links'] ?? [])) && count($data['external_links'] ?? []) === count($oldValues['external_links'] ?? [])) {
                    Log::info('UserController@update: User profile update called, but no actual data changed.', ['user_id' => $user->id]);
                } else {
                    Log::warning('UserController@update: User profile update returned false, but there might have been changes or issues.', [
                        'user_id' => $user->id,
                        'dirty_attributes_model' => $dirtyAttributes,
                        'data_submitted_for_external_links' => $data['external_links'] ?? [],
                        'old_external_links' => $oldValues['external_links'] ?? []
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('UserController@update: Exception during user update.', [
                'user_id' => $user->id, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', __('messages.profile_update_error_occurred'))->withInput();
        }


        $freshUser = $user->fresh();
        if ($freshUser) {
            Log::info('UserController@update: User locale from DB after update (fresh instance): "' . $freshUser->locale . '"');
        } else {
            Log::error('UserController@update: Could not get fresh user instance from DB.');
        }


        if (isset($data['locale']) && $freshUser && $oldValues['locale'] !== $freshUser->locale) {
            if ($freshUser->locale) {
                session(['locale' => $freshUser->locale]);
                Log::info('UserController@update: Session locale has been set to: "' . $freshUser->locale . '"');
            } else {
                session()->forget('locale');
                Log::info('UserController@update: Session locale has been forgotten (user locale is null).');
            }
        }


        Log::info('UserController@update: Process finished. Redirecting...');
        Log::info('--------------------------------------------------');

        Log::info('Attempting to update user ' . $user->id . ' with data:', $data);

        try {
            $user->update($data);
        } catch (Exception $e) {
            Log::error('Error updating user profile: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating the profile.')->withInput();
        }

        return redirect()->route('profile.edit')->with('success', __('messages.profile_updated_success'));
    }

    private function processAndStoreHeaderImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): string
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($uploadedFile->getRealPath());

        $image->scaleDown(width: self::HEADER_IMAGE_WIDTH);

        $newExtension = 'webp';
        $filename = $baseFilename . '.' . $newExtension;
        $path = $directory . '/' . $filename;

        $encodedImage = $image->encode(new WebpEncoder(quality: self::HEADER_IMAGE_QUALITY));

        Storage::disk('public')->put($path, $encodedImage);
        return $path;
    }

    private function processAndStoreProfileImage(UploadedFile $uploadedFile, string $directory, string $baseFilename): string
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($uploadedFile->getRealPath());

        $image->coverDown(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);

        $newExtension = 'webp';
        $filename = $baseFilename . '.' . $newExtension;
        $path = $directory . '/' . $filename;

        $encodedImage = $image->encode(new WebpEncoder(quality: self::PROFILE_IMAGE_QUALITY));

        Storage::disk('public')->put($path, $encodedImage);
        return $path;
    }

    final public function checkUsername(Request $request): JsonResponse
    {
        $username = $request->input('username');

        if (empty($username)) {
            return response()->json(['available' => false, 'message' => '']);
        }

        if (strlen($username) < 5) return response()->json(['available' => false, 'message' => __('validation.min.string', ['attribute' => 'username', 'min' => 5])]);
        if (strlen($username) > 24) return response()->json(['available' => false, 'message' => __('validation.max.string', ['attribute' => 'username', 'max' => 24])]);
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $username)) return response()->json(['available' => false, 'message' => __('validation.regex', ['attribute' => 'username'])]);
        if (preg_match('/^\d+$/', $username)) return response()->json(['available' => false, 'message' => __('validation.not_regex', ['attribute' => 'username'])]);
        if (preg_match('/(.)\1{3,}/', $username)) return response()->json(['available' => false, 'message' => __('validation.not_regex', ['attribute' => 'username']) . ' (no 4+ repeating chars)']);


        $query = User::where('username', $username);

        if (auth()->check()) {
            $query->where('id', '!=', auth()->id());
        }

        $exists = $query->exists();

        if ($exists) {
            return response()->json([
                'available' => false,
                'message' => __('messages.username_taken')
            ]);
        } else {
            $moderationResult = $this->moderationService->moderateText($username, Config::get('app.locale'));
            if (!$moderationResult['is_appropriate']) {
                return response()->json([
                    'available' => false,
                    'message' => $moderationResult['reason'] ?: __('messages.username_inappropriate')
                ]);
            }
            return response()->json([
                'available' => true,
                'message' => __('messages.username_available')
            ]);
        }
    }

    final public function generateProfilePicture(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|min:10|max:350',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $user = Auth::user();
        $today = Carbon::today();
        $lastGenerationDate = $user->last_ai_generation_date ? Carbon::parse($user->last_ai_generation_date) : null;

        if ($lastGenerationDate) {
            if (!$lastGenerationDate->isSameMonth($today)) {
                $user->ai_generations_monthly_count = 0;
            }
            if (!$lastGenerationDate->isSameDay($today)) {
                $user->ai_generations_daily_count = 0;
            }
        }

        $monthlyLimit = 5;
        $dailyLimit = 2;

        if ($user->ai_generations_monthly_count >= $monthlyLimit) {
            return response()->json(['error' => 'You have reached your monthly generation limit of 5.'], 429);
        }
        if ($user->ai_generations_daily_count >= $dailyLimit) {
            return response()->json(['error' => 'You have reached your daily generation limit of 2.'], 429);
        }

        try {
            $moderationLanguageCode = $user->locale ?? Config::get('app.locale');
            $promptModeration = $this->moderationService->moderateText($request->input('prompt'), $moderationLanguageCode);
            if (!$promptModeration['is_appropriate']) {
                $reason = $promptModeration['reason'] ?? 'The provided prompt is inappropriate.';
                return response()->json(['error' => $reason], 400);
            }

            $imageContent = $this->imageGenerationService->generateImageFromPrompt($request->input('prompt'));

            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $path = $this->processAndStoreProfileImageFromData($imageContent, 'profile_pictures', Str::slug($user->username) . '_aigen_' . time());

            $user->profile_picture = $path;
            $user->ai_generations_monthly_count += 1;
            $user->ai_generations_daily_count += 1;
            $user->last_ai_generation_date = $today->toDateString();
            $user->save();

            return response()->json([
                'success' => true,
                'new_image_url' => Storage::url($path),
                'monthly_remaining' => $monthlyLimit - $user->ai_generations_monthly_count,
                'daily_remaining' => $dailyLimit - $user->ai_generations_daily_count,
            ]);

        } catch (Exception $e) {
            Log::error('AI Profile Picture Generation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function processAndStoreProfileImageFromData(string $imageData, string $directory, string $baseFilename): string
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($imageData);
        $image->coverDown(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);
        $filename = $baseFilename . '.webp';
        $path = $directory . '/' . $filename;
        $encodedImage = $image->encode(new WebpEncoder(quality: self::PROFILE_IMAGE_QUALITY));
        Storage::disk('public')->put($path, $encodedImage);
        return $path;
    }


    final public function showSetPasswordForm(): View
    {
        if (Auth::user()->password) {
            return redirect()->route('profile.edit')->with('error', __('messages.error_password_already_set'));
        }
        return view('users.set-password');
    }


    final public function setPassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->password) {
            return redirect()->route('profile.edit')->with('error', 'You already have a password.');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        Auth::logoutOtherDevices($request->password);

        $request->session()->put('auth.password_confirmed_at', time());

        Log::channel('audit_trail')->info('User set their password for the first time.', [
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->intended(route('profile.edit'))
            ->with('success', __('messages.password_set_successfully'));
    }

    final public function linkSocial(string $provider): RedirectResponse
    {
        if (!in_array($provider, ['google', 'x', 'telegram'])) {
            abort(404);
        }

        session()->put('auth_link_redirect', route('profile.edit'));

        Log::channel('audit_trail')->info('User initiating social link.', [
            'user_id' => Auth::id(), 'provider' => $provider, 'ip_address' => request()->ip(),
        ]);

        return redirect()->route("auth.{$provider}.redirect");
    }

    final public function unlinkSocial(Request $request, string $provider): RedirectResponse
    {
        $user = Auth::user();

        if (!in_array($provider, ['google', 'x', 'telegram'])) {
            abort(404);
        }

        if ($user->getActiveAuthMethodsCount() <= 1) {
            return redirect()->route('profile.edit')->with('error', __('messages.error_cannot_unlink_last_auth'));
        }

        $provider_id_column = "{$provider}_id";
        if (is_null($user->$provider_id_column)) {
            return redirect()->route('profile.edit')->with('error', 'This account is not linked.');
        }

        $user->$provider_id_column = null;
        $user->save();

        Log::channel('audit_trail')->info('User unlinked a social provider.', [
            'user_id' => $user->id, 'provider' => $provider, 'ip_address' => $request->ip(),
        ]);

        return redirect()->route('profile.edit')->with('success', ucfirst($provider) . ' account has been unlinked.');
    }

}
