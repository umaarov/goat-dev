<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerificationService;
use App\Services\TelegramAuthService;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class AuthController extends Controller
{
    private AvatarService $avatarService;
    private EmailVerificationService $emailVerificationService;
    private TelegramAuthService $telegramAuthService;

    public function __construct(
        AvatarService            $avatarService,
        EmailVerificationService $emailVerificationService,
        TelegramAuthService      $telegramAuthService
    )
    {
        $this->avatarService = $avatarService;
        $this->emailVerificationService = $emailVerificationService;
        $this->telegramAuthService = $telegramAuthService;
    }

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), $this->getRegistrationRules());

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }

        try {
            $user = $this->createUser($request);
            $this->setProfilePicture($user, $request);
            $this->emailVerificationService->sendVerificationEmail($user);

            Log::channel('audit_trail')->info('User registration initiated.', [
                'attempted_email' => $request->email,
                'attempted_username' => $request->username,
                'user_id_created' => $user->id,
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->with('success', __('messages.registration_successful_verify_email'));
        } catch (Exception $e) {
            Log::channel('audit_trail')->error('User registration failed.', [
                'attempted_email' => $request->email,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);
            Log::error('Registration system error: ' . $e->getMessage(), [
                'user_email' => $request->email,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => __('messages.error_registration_failed')])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }

    private function getRegistrationRules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => [
                'required',
                'string',
                'min:5',
                'max:24',
                'unique:users',
                'regex:/^[a-zA-Z][a-zA-Z0-9_-]*$/',
                'not_regex:/^\d+$/',
                'not_regex:/(.)\1{3,}/',
            ],
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'terms' => 'accepted',
        ];
    }

    private function createUser(Request $request): User
    {
        return User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_picture' => null,
            'email_verification_token' => $this->emailVerificationService->generateToken(),
        ]);
    }

    private function setProfilePicture(User $user, Request $request): void
    {
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');
        } else {
            $profilePicturePath = $this->avatarService->generateInitialsAvatar(
                $user->first_name,
                $user->last_name ?? '',
                $user->id
            );
        }

        $user->profile_picture = $profilePicturePath;
        $user->save();
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect()->route('verification.notice')
                ->with('error', __('messages.error_invalid_verification_link'));
        }

        $user = User::findOrFail($request->id);

        if ($user->hasVerifiedEmail()) {
            return Auth::check()
                ? redirect()->route('home')->with('success', __('messages.email_already_verified'))
                : redirect()->route('login')->with('success', __('messages.email_already_verified'));
        }

        if ($this->emailVerificationService->verify($user, $request->token)) {
            Log::channel('audit_trail')->info('User email verified.', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);
            event(new UserRegistered($user));

            return Auth::check()
                ? redirect()->route('home')->with('success', __('messages.email_verified_success'))
                : redirect()->route('login')->with('success', __('messages.email_verified_can_login'));
        }

        Log::channel('audit_trail')->warning('Email verification failed (invalid token/link).', [
            'attempted_user_id' => $request->id,
            'token_used' => $request->token,
            'ip_address' => $request->ip(),
            'signature_valid' => $request->hasValidSignature()
        ]);

        return redirect()->route('verification.notice')
            ->with('error', __('messages.error_invalid_verification_token'));
    }

    public function showVerificationNotice(): View
    {
        return view('auth.verify');
    }

    public function resendVerificationEmail(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $this->emailVerificationService->sendVerificationEmail($user);
        return back()->with('success', __('messages.verification_link_sent'));
    }

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function googleRedirect()
    {
        try {
            Log::channel('audit_trail')->info('Redirecting to Google for authentication.', [
                'time' => microtime(true)
            ]);
            return Socialite::driver('google')->redirect();
        } catch (Exception $e) {
            Log::error('Google redirect failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('login')->with('error', __('messages.error_google_auth_failed'));
        }
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        if (Auth::check() && session()->has('auth_link_redirect')) {
            $redirectRoute = session()->pull('auth_link_redirect', route('profile.edit'));
            $user = Auth::user();

            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
                $existingUser = User::where('google_id', $googleUser->getId())->where('id', '!=', $user->id)->first();

                if ($existingUser) {
                    return redirect($redirectRoute)->with('error', 'This Google account is already linked to another user.');
                }

                $user->google_id = $googleUser->getId();
                $user->save();

                Log::channel('audit_trail')->info('User linked Google account.', [
                    'user_id' => $user->id, 'google_id' => $googleUser->getId(), 'ip_address' => $request->ip(),
                ]);

                return redirect($redirectRoute)->with('success', 'Successfully linked your Google account.');
            } catch (Exception $e) {
                Log::error('Google account linking failed for user: ' . $user->id, ['error' => $e->getMessage()]);
                return redirect($redirectRoute)->with('error', 'Failed to link Google account. Please try again.');
            }
        }
        $time_start_callback = microtime(true);
        Log::channel('audit_trail')->info('Google callback initiated.', [
            'ip_address' => $request->ip(),
            'query_params' => $request->query(),
            'time_start_callback' => $time_start_callback
        ]);

        try {
            if ($request->has('error')) {
                Log::channel('audit_trail')->error('Google callback returned an error.', [
                    'error' => $request->input('error'),
                    'error_description' => $request->input('error_description'),
                    'ip_address' => $request->ip(),
                    'time' => microtime(true),
                ]);
                return redirect()->route('login')
                    ->with('error', __('messages.error_google_auth_denied_or_failed'));
            }

            if (!$request->has('code')) {
                Log::channel('audit_trail')->error('Google callback missing authorization code.', [
                    'ip_address' => $request->ip(),
                    'query_params' => $request->query(),
                    'time' => microtime(true),
                ]);
                return redirect()->route('login')
                    ->with('error', __('messages.error_google_auth_failed') . ' (Missing authorization code)');
            }

            $time_before_socialite_user = microtime(true);
            Log::channel('audit_trail')->info('Attempting to fetch Google user from Socialite.', ['time' => $time_before_socialite_user]);

            $googleUser = Socialite::driver('google')->stateless()->user();

            $time_after_socialite_user = microtime(true);
            $duration_socialite_user_call = $time_after_socialite_user - $time_before_socialite_user;
            Log::channel('audit_trail')->info('Successfully fetched Google user from Socialite.', [
                'google_user_id' => $googleUser->getId(),
                'google_user_email' => $googleUser->getEmail(),
                'time' => $time_after_socialite_user,
                'duration_socialite_user_call_seconds' => $duration_socialite_user_call
            ]);

            $time_before_db_lookup = microtime(true);
            $userModel = User::where('google_id', $googleUser->getId())->first();
            $time_after_db_lookup = microtime(true);
            Log::channel('audit_trail')->info('DB lookup for existing Google ID.', [
                'duration_db_lookup_seconds' => $time_after_db_lookup - $time_before_db_lookup,
                'found_user_by_google_id' => !is_null($userModel)
            ]);

            $action = "Logged in";
            $initialUserModelNull = is_null($userModel);

            if (!$userModel) {
                $time_before_handle_user = microtime(true);
                Log::channel('audit_trail')->info('No existing user by Google ID. Calling handleGoogleUser.', [
                    'google_email' => $googleUser->getEmail(),
                    'time' => $time_before_handle_user
                ]);

                $userModel = $this->handleGoogleUser($googleUser);

                $time_after_handle_user = microtime(true);
                Log::channel('audit_trail')->info('Finished handleGoogleUser call.', [
                    'user_id_returned' => $userModel->id,
                    'was_recently_created' => $userModel->wasRecentlyCreated,
                    'duration_handle_user_seconds' => $time_after_handle_user - $time_before_handle_user
                ]);

                if ($userModel->wasRecentlyCreated) {
                    $action = 'Registered and logged in';
                    event(new UserRegistered($userModel));
                } elseif ($initialUserModelNull && $userModel->google_id == $googleUser->getId()) {
                    $action = 'Logged in (linked Google to existing email account)';
                } else {
                    $action = 'Logged in (handleGoogleUser resolved)';
                }
            } else {
                $action = "Logged in (existing Google ID)";
                // $userModel = $this->syncGoogleUserData($userModel, $googleUser);
            }

            $user = $userModel;

            Auth::login($user, true);
            $request->session()->regenerate();

            $time_end_callback = microtime(true);
            $total_callback_duration = $time_end_callback - $time_start_callback;
            Log::channel('audit_trail')->info("User $action via Google.", [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'google_id' => $googleUser->getId(),
                'ip_address' => $request->ip(),
                'time_end_callback' => $time_end_callback,
                'total_callback_duration_seconds' => $total_callback_duration
            ]);

            return redirect()->intended(route('home'))->with('success', __('messages.google_login_success'));

        } catch (InvalidStateException $e) {
            Log::channel('audit_trail')->error('Google authentication failed: Invalid State.', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'time_exception' => microtime(true),
            ]);
            Log::error('Google Auth Invalid State Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);
            return redirect()->route('login')
                ->with('error', __('messages.error_google_auth_failed_state') ?: 'Google authentication failed due to an invalid state. Please try again.');
        } catch (ConnectException $e) {
            Log::channel('audit_trail')->error('Google authentication failed: Connection issue (Guzzle).', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'time_exception' => microtime(true),
            ]);
            Log::error('Google Auth Guzzle ConnectException', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);
            return redirect()->route('login')
                ->with('error', __('messages.error_google_auth_network') ?: 'Could not connect to Google for authentication. Please check your internet connection and try again.');
        } catch (Exception $e) {
            Log::channel('audit_trail')->error('Google authentication/callback failed with generic Exception.', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'ip_address' => $request->ip(),
                'time_exception' => microtime(true),
            ]);
            Log::error('Google Auth System Error (Generic Exception)', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);

            return redirect()->route('login')
                ->with('error', __('messages.error_google_login_failed'));
        }
    }

    private function handleGoogleUser($googleUser): User
    {
        $time_start_handle = microtime(true);
        Log::channel('audit_trail')->info('Inside handleGoogleUser.', [
            'google_email' => $googleUser->getEmail(),
            'google_id_from_socialite' => $googleUser->getId(),
            'time_start' => $time_start_handle
        ]);

        $existingUserByEmail = User::where('email', $googleUser->getEmail())->first();
        $time_after_email_lookup = microtime(true);
        Log::channel('audit_trail')->info('DB lookup for existing email in handleGoogleUser.', [
            'duration_email_lookup_seconds' => $time_after_email_lookup - $time_start_handle,
            'found_user_by_email' => !is_null($existingUserByEmail)
        ]);

        if ($existingUserByEmail) {
            Log::channel('audit_trail')->info('Existing user found by email in handleGoogleUser. Updating with Google ID if not set.', [
                'user_id' => $existingUserByEmail->id,
                'current_google_id_on_user' => $existingUserByEmail->google_id
            ]);
            $userToReturn = $this->updateExistingUserWithGoogle($existingUserByEmail, $googleUser);
            $log_action = 'updated_existing_user_with_google_info';
        } else {
            Log::channel('audit_trail')->info('No existing user by email in handleGoogleUser. Creating new user from Google info.');
            $userToReturn = $this->createUserFromGoogle($googleUser);
            $log_action = 'created_new_user_from_google_info';
        }

        $time_end_handle = microtime(true);
        Log::channel('audit_trail')->info('Finished handleGoogleUser.', [
            'user_id_processed' => $userToReturn->id,
            'action_taken' => $log_action,
            'was_recently_created_flag' => $userToReturn->wasRecentlyCreated,
            'final_google_id_on_user' => $userToReturn->google_id,
            'duration_handle_user_total_seconds' => $time_end_handle - $time_start_handle,
        ]);

        return $userToReturn;
    }

    private function updateExistingUserWithGoogle(User $user, $googleUser): User
    {
        if (is_null($user->google_id)) {
            $user->google_id = $googleUser->getId();
        }
        // $user->google_id = $googleUser->getId();

        if (!$user->profile_picture && $googleUser->getAvatar()) {
            $user->profile_picture = $googleUser->getAvatar();
        }

        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();
        Log::channel('audit_trail')->info('Updated existing user with Google info.', ['user_id' => $user->id, 'google_id_set' => $user->google_id]);
        return $user;
    }

    private function createUserFromGoogle($googleUser): User
    {
        $time_start_create = microtime(true);
        Log::channel('audit_trail')->info('Creating new user from Google data.', [
            'google_email' => $googleUser->getEmail(),
            'google_name' => $googleUser->getName(),
            'time_start' => $time_start_create
        ]);

        $username = $this->generateUniqueUsername($googleUser->getName());

        $name = $googleUser->getName();
        $nameParts = explode(' ', $name, 2);
        $firstName = $googleUser->user['given_name'] ?? $nameParts[0] ?? Str::studly(Str::before($googleUser->getEmail(), '@')); // Fallback for first name
        $lastName = $googleUser->user['family_name'] ?? ($nameParts[1] ?? null);

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
//            'password' => Hash::make(Str::random(24)),
            'password' => null,
        ]);
        Log::channel('audit_trail')->info('User model created in DB.', ['user_id' => $user->id, 'username' => $username, 'time_after_eloquent_create' => microtime(true)]);


        if ($googleUser->getAvatar()) {
            $user->profile_picture = $googleUser->getAvatar();
        } else {
            $profilePicturePath = $this->avatarService->generateInitialsAvatar(
                $user->first_name,
                $user->last_name ?? '',
                $user->id
            );
            $user->profile_picture = $profilePicturePath;
        }
        $user->save();

        $time_end_create = microtime(true);
        Log::channel('audit_trail')->info('Finished creating user from Google and saved profile picture.', [
            'user_id' => $user->id,
            'duration_create_user_seconds' => $time_end_create - $time_start_create
        ]);

        return $user;
    }

    private function generateUniqueUsername(string $name, ?int $telegramId = null): string
    {
        $time_start = microtime(true);
        $maxLength = 24;
        $minLength = 5;

        $baseUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);

        if (empty($baseUsername)) {
            $baseUsername = $telegramId ? 'user' . $telegramId : 'user' . Str::random(8);
        }

        if (!preg_match('/^[a-zA-Z]/', $baseUsername)) {
            $baseUsername = 'u' . $baseUsername;
        }

        if (strlen($baseUsername) < $minLength) {
            $baseUsername .= Str::lower(Str::random($minLength - strlen($baseUsername)));
        }
        $baseUsername = substr($baseUsername, 0, 20);

        $cacheKey = 'username_check_' . md5($baseUsername);
        if (Cache::has($cacheKey)) {
            $cachedUsername = Cache::get($cacheKey);
            if (!User::where('username', $cachedUsername)->exists()) {
                Log::channel('audit_trail')->debug('Using cached unique username.', ['username' => $cachedUsername]);
                return $cachedUsername;
            }
            Cache::forget($cacheKey);
        }

        $username = $baseUsername;
        $counter = 1;
        $attempt = 0;
        $maxAttempts = 1000;

        while (User::where('username', $username)->exists()) {
            if ($attempt++ >= $maxAttempts) {
                Log::channel('audit_trail')->warning('Max attempts reached, generating random username.', ['base' => $baseUsername]);
                do {
                    $username = 'user' . Str::lower(Str::random(10));
                } while (User::where('username', $username)->exists());
                break;
            }

            $suffix = '_' . $counter++;
            $trimLength = $maxLength - strlen($suffix);
            $username = substr($baseUsername, 0, $trimLength) . $suffix;
        }

        Cache::put($cacheKey, $username, now()->addMinutes(10));
        Log::channel('audit_trail')->info('Generated unique username.', [
            'original_name' => $name,
            'base_username' => $baseUsername,
            'generated_username' => $username,
            'attempts' => $attempt,
            'duration_seconds' => microtime(true) - $time_start
        ]);

        return $username;
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login_identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->input('login_identifier');
        $password = $request->input('password');
//        $remember = $request->filled('remember');
        $remember = true;

        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $fieldType => $loginInput,
            'password' => $password,
        ];

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if (!$user->email_verified_at) {
                Auth::logout();
                Log::channel('audit_trail')->warning('Login attempt failed: Email not verified.', [
                    'login_identifier' => $loginInput,
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                ]);
                return redirect()->route('login')
                    ->withErrors(['login_identifier' => __('messages.error_email_not_verified_login')])
                    ->withInput($request->only('login_identifier'));
            }

            $request->session()->regenerate();
            Log::debug('DEBUG LOGIN: User ' . $user->username . ' successfully authenticated. About to log to audit_trail.');
            Log::channel('audit_trail')->info('User authenticated successfully.', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip_address' => $request->ip(),
            ]);
            return redirect()->intended(route('home'))->with('success', __('messages.logged_in_successfully'));
        }

        Log::channel('audit_trail')->warning('Failed login attempt: Invalid credentials.', [
            'login_identifier' => $loginInput,
            'ip_address' => $request->ip(),
        ]);
        Log::warning('Failed login attempt', ['login_identifier' => $loginInput, 'ip' => $request->ip()]);

        return redirect()->back()
            ->withErrors(['login_identifier' => __('messages.error_invalid_login_credentials')])
            ->withInput($request->only('login_identifier'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            Log::channel('audit_trail')->info('User logged out.', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip_address' => $request->ip(),
            ]);
        } else {
            Log::channel('audit_trail')->info('Logout attempt by unauthenticated session.', [
                'ip_address' => $request->ip(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', __('messages.logged_out_successfully'));
    }

    public function showConfirmForm(): View
    {
        return view('auth.confirm-password');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Auth::guard('web')->validate(['email' => Auth::user()->email, 'password' => $request->password])) {
            return back()->withErrors(['password' => __('auth.password')]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('profile.sessions.terminate_all'));
    }

    public function showLinkRequestForm(): View
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            Log::channel('audit_trail')->info('Password reset link sent.', [
                'email' => $request->email,
                'ip_address' => $request->ip(),
            ]);
            return back()->with('success', __($status));
        }

        Log::channel('audit_trail')->warning('Password reset link failed to send.', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'status' => $status,
        ]);
        return back()->withErrors(['email' => __($status)]);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.passwords.reset')->with([
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();

                Auth::logoutOtherDevices($password);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            Log::channel('audit_trail')->info('User password has been reset.', [
                'email' => $request->email,
                'ip_address' => $request->ip(),
            ]);
            return redirect()->route('login')->with('success', __($status));
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    public function xRedirect()
    {
        try {
            $scopes = ['users.read', 'tweet.read'];

            Log::channel('audit_trail')->info('Redirecting to X for authentication.', [
                'time' => microtime(true),
                'scopes_requested' => $scopes
            ]);

            return Socialite::driver('x')->scopes($scopes)->redirect();

        } catch (Exception $e) {
            Log::error('X redirect failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('login')->with('error', __('messages.error_x_auth_failed'));
        }
    }

    public function xCallback(Request $request): RedirectResponse
    {
        if (Auth::check() && session()->has('auth_link_redirect')) {
            $redirectRoute = session()->pull('auth_link_redirect', route('profile.edit'));
            $user = Auth::user();

            try {
                $xUser = Socialite::driver('x')->user();
                $existingUser = User::where('x_id', $xUser->getId())->where('id', '!=', $user->id)->first();

                if ($existingUser) {
                    return redirect($redirectRoute)->with('error', 'This X account is already linked to another user.');
                }

                $user->x_id = $xUser->getId();
                $user->save();

                Log::channel('audit_trail')->info('User linked X account.', [
                    'user_id' => $user->id, 'x_id' => $xUser->getId(), 'ip_address' => $request->ip(),
                ]);
                return redirect($redirectRoute)->with('success', 'Successfully linked your X account.');
            } catch (Exception $e) {
                Log::error('X account linking failed for user: ' . $user->id, ['error' => $e->getMessage()]);
                return redirect($redirectRoute)->with('error', 'Failed to link X account. Please try again.');
            }
        }
        $time_start_callback = microtime(true);
        Log::channel('audit_trail')->info('X callback initiated.', [
            'ip_address' => $request->ip(),
            'query_params' => $request->query(),
            'time_start_callback' => $time_start_callback
        ]);

        try {
            if ($request->has('error')) {
                Log::channel('audit_trail')->error('X callback returned an error.', [
                    'error' => $request->input('error'),
                    'error_description' => $request->input('error_description'),
                    'ip_address' => $request->ip(),
                    'time' => microtime(true),
                ]);
                return redirect()->route('login')
                    ->with('error', __('messages.error_x_auth_denied_or_failed'));
            }

            if (!$request->has('code')) {
                Log::channel('audit_trail')->error('X callback missing authorization code.', [
                    'ip_address' => $request->ip(),
                    'query_params' => $request->query(),
                    'time' => microtime(true),
                ]);
                return redirect()->route('login')
                    ->with('error', __('messages.error_x_auth_failed') . ' (Missing authorization code)');
            }

            $time_before_socialite_user = microtime(true);
            Log::channel('audit_trail')->info('Attempting to fetch X user from Socialite.', ['time' => $time_before_socialite_user]);

            $xUser = Socialite::driver('x')->user();

            $time_after_socialite_user = microtime(true);
            $duration_socialite_user_call = $time_after_socialite_user - $time_before_socialite_user;
            Log::channel('audit_trail')->info('Successfully fetched X user from Socialite.', [
                'x_user_id' => $xUser->getId(),
                'x_user_email' => $xUser->getEmail(),
                'time' => $time_after_socialite_user,
                'duration_socialite_user_call_seconds' => $duration_socialite_user_call
            ]);

            $time_before_db_lookup = microtime(true);
            $userModel = User::where('x_id', $xUser->getId())->first();
            $time_after_db_lookup = microtime(true);
            Log::channel('audit_trail')->info('DB lookup for existing X ID.', [
                'duration_db_lookup_seconds' => $time_after_db_lookup - $time_before_db_lookup,
                'found_user_by_x_id' => !is_null($userModel)
            ]);

            $action = "Logged in";
            $initialUserModelNull = is_null($userModel);

            if (!$userModel) {
                $time_before_handle_user = microtime(true);
                Log::channel('audit_trail')->info('No existing user by X ID. Calling handleXUser.', [
                    'x_email' => $xUser->getEmail(),
                    'time' => $time_before_handle_user
                ]);

                $userModel = $this->handleXUser($xUser);

                $time_after_handle_user = microtime(true);
                Log::channel('audit_trail')->info('Finished handleXUser call.', [
                    'user_id_returned' => $userModel->id,
                    'was_recently_created' => $userModel->wasRecentlyCreated,
                    'duration_handle_user_seconds' => $time_after_handle_user - $time_before_handle_user
                ]);

                if ($userModel->wasRecentlyCreated) {
                    $action = 'Registered and logged in';
                    event(new UserRegistered($userModel));
                } elseif ($initialUserModelNull && $userModel->x_id == $xUser->getId()) {
                    $action = 'Logged in (linked X to existing email account)';
                } else {
                    $action = 'Logged in (handleXUser resolved)';
                }
            } else {
                $action = "Logged in (existing X ID)";
            }

            $user = $userModel;

            Auth::login($user, true);
            $request->session()->regenerate();

            $time_end_callback = microtime(true);
            $total_callback_duration = $time_end_callback - $time_start_callback;
            Log::channel('audit_trail')->info("User $action via X.", [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'x_id' => $xUser->getId(),
                'ip_address' => $request->ip(),
                'time_end_callback' => $time_end_callback,
                'total_callback_duration_seconds' => $total_callback_duration
            ]);

            return redirect()->intended(route('home'))->with('success', __('messages.x_login_success'));

        } catch (InvalidStateException $e) {
            Log::channel('audit_trail')->error('X authentication failed: Invalid State.', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'time_exception' => microtime(true),
            ]);
            Log::error('X Auth Invalid State Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);
            return redirect()->route('login')
                ->with('error', __('messages.error_x_auth_failed_state') ?: 'X authentication failed due to an invalid state. Please try again.');
        } catch (ConnectException $e) {
            Log::channel('audit_trail')->error('X authentication failed: Connection issue (Guzzle).', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'time_exception' => microtime(true),
            ]);
            Log::error('X Auth Guzzle ConnectException', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);
            return redirect()->route('login')
                ->with('error', __('messages.error_x_auth_network') ?: 'Could not connect to X for authentication. Please check your internet connection and try again.');
        } catch (Exception $e) {
            Log::channel('audit_trail')->error('X authentication/callback failed with generic Exception.', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'ip_address' => $request->ip(),
                'time_exception' => microtime(true),
            ]);
            Log::error('X Auth System Error (Generic Exception)', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);

            return redirect()->route('login')
                ->with('error', __('messages.error_x_login_failed'));
        }
    }

    private function handleXUser($xUser): User
    {
        $time_start_handle = microtime(true);
        Log::channel('audit_trail')->info('Inside handleXUser.', [
            'x_email' => $xUser->getEmail(),
            'x_id_from_socialite' => $xUser->getId(),
            'time_start' => $time_start_handle
        ]);

        $existingUserByEmail = User::where('email', $xUser->getEmail())->first();
        $time_after_email_lookup = microtime(true);
        Log::channel('audit_trail')->info('DB lookup for existing email in handleXUser.', [
            'duration_email_lookup_seconds' => $time_after_email_lookup - $time_start_handle,
            'found_user_by_email' => !is_null($existingUserByEmail)
        ]);

        if ($existingUserByEmail) {
            Log::channel('audit_trail')->info('Existing user found by email in handleXUser. Updating with X ID if not set.', [
                'user_id' => $existingUserByEmail->id,
                'current_x_id_on_user' => $existingUserByEmail->x_id
            ]);
            $userToReturn = $this->updateExistingUserWithX($existingUserByEmail, $xUser);
            $log_action = 'updated_existing_user_with_x_info';
        } else {
            Log::channel('audit_trail')->info('No existing user by email in handleXUser. Creating new user from X info.');
            $userToReturn = $this->createUserFromX($xUser);
            $log_action = 'created_new_user_from_x_info';
        }

        $time_end_handle = microtime(true);
        Log::channel('audit_trail')->info('Finished handleXUser.', [
            'user_id_processed' => $userToReturn->id,
            'action_taken' => $log_action,
            'was_recently_created_flag' => $userToReturn->wasRecentlyCreated,
            'final_x_id_on_user' => $userToReturn->x_id,
            'duration_handle_user_total_seconds' => $time_end_handle - $time_start_handle,
        ]);

        return $userToReturn;
    }

    private function updateExistingUserWithX(User $user, $xUser): User
    {
        if (is_null($user->x_id)) {
            $user->x_id = $xUser->getId();
        }

        $user->x_username = $xUser->getNickname();

        if (!$user->profile_picture && $xUser->getAvatar()) {
            $user->profile_picture = $xUser->getAvatar();
        }

        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();
        Log::channel('audit_trail')->info('Updated existing user with X info.', ['user_id' => $user->id, 'x_id_set' => $user->x_id]);
        return $user;
    }

    private function createUserFromX($xUser): User
    {
        $time_start_create = microtime(true);
        Log::channel('audit_trail')->info('Creating new user from X data.', [
            'x_email' => $xUser->getEmail(),
            'x_name' => $xUser->getName(),
            'time_start' => $time_start_create
        ]);

        $username = $this->generateUniqueUsername($xUser->getName() ?: $xUser->getNickname() ?: 'user');

        $name = $xUser->getName() ?: $xUser->getNickname() ?: '';
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? Str::studly(Str::before($xUser->getEmail() ?: 'user@x.com', '@'));
        $lastName = $nameParts[1] ?? null;

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $xUser->getEmail() ?: $username . '@x-user.local',
            'x_id' => $xUser->getId(),
            'email_verified_at' => now(),
//            'password' => Hash::make(Str::random(24)),
            'password' => null,
        ]);

        $user->x_username = $xUser->getNickname();

        Log::channel('audit_trail')->info('User model created in DB.', ['user_id' => $user->id, 'username' => $username, 'time_after_eloquent_create' => microtime(true)]);

        if ($xUser->getAvatar()) {
            $user->profile_picture = $xUser->getAvatar();
        } else {
            $profilePicturePath = $this->avatarService->generateInitialsAvatar(
                $user->first_name,
                $user->last_name ?? '',
                $user->id
            );
            $user->profile_picture = $profilePicturePath;
        }
        $user->save();

        $time_end_create = microtime(true);
        Log::channel('audit_trail')->info('Finished creating user from X and saved profile picture.', [
            'user_id' => $user->id,
            'duration_create_user_seconds' => $time_end_create - $time_start_create
        ]);

        return $user;
    }

    public function telegramRedirect(): RedirectResponse
    {
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            return redirect()->route('login')->with('error', 'Telegram login is not configured.');
        }

        $botId = explode(':', $botToken, 2)[0];

        $telegramAuthUrl = "https://oauth.telegram.org/auth?" . http_build_query([
                'bot_id' => $botId,
                'origin' => config('app.url'),
                'redirect_to' => route('auth.callback', 'telegram'),
                'request_access' => 'write',
            ]);

        return Redirect::to($telegramAuthUrl);
    }

    public function telegramCallback(Request $request): RedirectResponse
    {
        if (Auth::check() && session()->has('auth_link_redirect')) {
            $redirectRoute = session()->pull('auth_link_redirect', route('profile.edit'));
            $user = Auth::user();

            try {
                $telegramUser = $this->telegramAuthService->validate($request->all());
                if (!$telegramUser) {
                    return redirect($redirectRoute)->with('error', 'Telegram authentication failed.');
                }
                $existingUser = User::where('telegram_id', $telegramUser['id'])->where('id', '!=', $user->id)->first();

                if ($existingUser) {
                    return redirect($redirectRoute)->with('error', 'This Telegram account is already linked to another user.');
                }

                $user->telegram_id = $telegramUser['id'];
                $user->save();

                Log::channel('audit_trail')->info('User linked Telegram account.', [
                    'user_id' => $user->id, 'telegram_id' => $telegramUser['id'], 'ip_address' => $request->ip(),
                ]);
                return redirect($redirectRoute)->with('success', 'Successfully linked your Telegram account.');
            } catch (Exception $e) {
                Log::error('Telegram account linking failed for user: ' . $user->id, ['error' => $e->getMessage()]);
                return redirect($redirectRoute)->with('error', 'Failed to link Telegram account. Please try again.');
            }
        }

        $time_start_callback = microtime(true);
        Log::channel('audit_trail')->info('Telegram callback initiated.', [
            'ip_address' => $request->ip(),
            'query_params' => $request->query(),
        ]);

        try {
            $telegramUser = $this->telegramAuthService->validate($request->all());

            if (!$telegramUser) {
                Log::channel('audit_trail')->error('Telegram authentication failed: Invalid data or hash.');
                return redirect()->route('login')->with('error', __('messages.error_telegram_auth_failed'));
            }

            $userModel = $this->handleTelegramUser($telegramUser);

            $action = $userModel->wasRecentlyCreated ? 'Registered and logged in' : 'Logged in';

            if ($userModel->wasRecentlyCreated) {
                event(new UserRegistered($userModel));
            }

            Auth::login($userModel, true);
            $request->session()->regenerate();

            Log::channel('audit_trail')->info("User $action via Telegram.", [
                'user_id' => $userModel->id,
                'username' => $userModel->username,
                'telegram_id' => $telegramUser['id'],
                'ip_address' => $request->ip(),
                'total_callback_duration_seconds' => microtime(true) - $time_start_callback,
            ]);

//            return redirect()->intended(route('home'))->with('success', __('messages.telegram_login_success'));
            return redirect()->route('home')->with('success', __('messages.telegram_login_success'));

        } catch (Exception $e) {
            Log::channel('audit_trail')->error('Telegram authentication/callback failed with generic Exception.', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'ip_address' => $request->ip(),
            ]);
            Log::error('Telegram Auth System Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip()
            ]);

            return redirect()->route('login')->with('error', __('messages.error_telegram_login_failed'));
        }
    }


    private function handleTelegramUser(array $telegramUser): User
    {
        $user = User::firstOrNew(['telegram_id' => $telegramUser['id']]);

        if (!$user->exists) {
            $baseName = $telegramUser['username'] ?? ($telegramUser['first_name'] . ($telegramUser['last_name'] ?? ''));

            $firstName = preg_replace('/[^\\p{L}\\p{N}\\s]/u', '', $telegramUser['first_name']);
            $lastName = preg_replace('/[^\\p{L}\\p{N}\\s]/u', '', $telegramUser['last_name'] ?? '');

            $user->fill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $this->generateUniqueUsername($baseName, $telegramUser['id']),
                'email' => $telegramUser['id'] . '@telegram-user.local',
                'email_verified_at' => now(),
//                'password' => Hash::make(Str::random(24)),
                'password' => null,
            ]);

            $user->telegram_username = $telegramUser['username'] ?? null;

            $user->save();

            if (!empty($telegramUser['photo_url'])) {
                $user->profile_picture = $telegramUser['photo_url'];
            } else {
                $user->profile_picture = $this->avatarService->generateInitialsAvatar(
                    $user->first_name,
                    $user->last_name ?? '',
                    $user->id
                );
            }

            $user->save();
        }

        if (is_null($user->telegram_username) && isset($telegramUser['username'])) {
            $user->telegram_username = $telegramUser['username'];
        }

        $user->telegram_id = $telegramUser['id'];
        $user->save();

        return $user;
    }

    public function socialRedirect(string $provider): RedirectResponse
    {
        if ($provider === 'telegram') {
            $botToken = config('services.telegram.bot_token');
            $botId = explode(':', $botToken, 2)[0];
            $url = "https://oauth.telegram.org/auth?" . http_build_query([
                    'bot_id' => $botId,
                    'origin' => config('app.url'),
                    'redirect_to' => route('auth.callback', ['provider' => 'telegram']),
                    'request_access' => 'write',
                ]);
            return Redirect::to($url);
        }

        $scopes = ($provider === 'x') ? ['users.read', 'tweet.read', 'offline.access'] : [];
        return Socialite::driver($provider)->scopes($scopes)->redirect();
    }

    public function socialCallback(Request $request, string $provider): RedirectResponse
    {
        try {
            $socialUser = null;

            if ($provider === 'telegram') {
                $telegramData = $this->telegramAuthService->validate($request->all());
                if (!$telegramData) throw new Exception('Telegram authentication failed. Invalid data.');

                $socialUser = (object) [
                    'id' => $telegramData['id'],
                    'name' => trim($telegramData['first_name'] . ' ' . ($telegramData['last_name'] ?? '')),
                    'email' => null,
                    'avatar' => $telegramData['photo_url'] ?? null,
                    'nickname' => $telegramData['username'] ?? null
                ];

            } else {
                $socialUser = Socialite::driver($provider)->stateless()->user();
            }

            Log::info("Socialite user data for {$provider}:", (array) $socialUser);

            if (Auth::check()) {
                $user = Auth::user();

                $existingUser = User::where("{$provider}_id", $socialUser->id)->where('id', '!=', $user->id)->first();
                if ($existingUser) {
                    return redirect()->route('profile.edit')->with('error', "This {$provider} account is already linked to another user.");
                }

                $updateData = [
                    "{$provider}_id" => $socialUser->id,
                ];

                if ($provider === 'x' && !empty($socialUser->nickname)) {
                    $updateData['x_username'] = $socialUser->nickname;
                }
                elseif ($provider === 'telegram' && !empty($socialUser->nickname)) {
                    $updateData['telegram_username'] = $socialUser->nickname;
                }

                $user->forceFill($updateData)->save();

                Log::channel('audit_trail')->info("User {$user->username} linked their {$provider} account.");
                return redirect()->route('profile.edit')->with('success', "Successfully linked your {$provider} account.");
            }


            $user = User::where("{$provider}_id", $socialUser->id)->first();
            if ($user) {
                Auth::login($user, true);
                Log::channel('audit_trail')->info("User {$user->username} logged in via {$provider}.");
                return redirect()->intended(route('home'));
            }

            if ($socialUser->email) {
                $user = User::where('email', $socialUser->email)->first();
                if ($user) {
                    $user->forceFill(["{$provider}_id" => $socialUser->id])->save();
                    Auth::login($user, true);
                    Log::channel('audit_trail')->info("User {$user->username} logged in via {$provider} (linked to existing email).");
                    return redirect()->intended(route('home'));
                }
            }

            $newUser = $this->createUserFromSocial($provider, $socialUser);
            Auth::login($newUser, true);
            Log::channel('audit_trail')->info("New user {$newUser->username} registered and logged in via {$provider}.");

            return redirect()->intended(route('home'));

        } catch (Exception $e) {
            Log::error("{$provider} auth callback failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }

    private function createUserFromSocial(string $provider, object $socialUser): User
    {
        $username = $this->generateUniqueUsername($socialUser->name ?: $socialUser->nickname);

        $nameParts = explode(' ', $socialUser->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? null;

        $email = $socialUser->email ?: $socialUser->id . "@{$provider}-user.local";
        if (User::where('email', $email)->exists()) {
            $email = $socialUser->id . '_' . Str::random(5) . "@{$provider}-user.local";
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email,
            "{$provider}_id" => $socialUser->id,
            'email_verified_at' => now(),
            'password' => null,
        ]);

        if ($socialUser->avatar) {
            $user->profile_picture = $socialUser->avatar;
        } else {
            $user->profile_picture = $this->avatarService->generateInitialsAvatar($firstName, $lastName ?? '', $user->id);
        }
        $user->save();

        return $user;
    }
}

