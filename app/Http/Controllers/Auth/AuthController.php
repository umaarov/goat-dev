<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerificationService;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class AuthController extends Controller
{
    private AvatarService $avatarService;
    private EmailVerificationService $emailVerificationService;

    public function __construct(AvatarService $avatarService, EmailVerificationService $emailVerificationService)
    {
        $this->avatarService = $avatarService;
        $this->emailVerificationService = $emailVerificationService;
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

    public function verifyEmail(Request $request): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect()->route('verification.notice')
                ->with('error', __('messages.error_invalid_verification_link'));
        }

        $user = User::findOrFail($request->id);

        if ($this->emailVerificationService->verify($user, $request->token)) {
            Log::channel('audit_trail')->info('User email verified.', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);
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

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login_identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = $request->input('login_identifier');
        $password = $request->input('password');
        $remember = $request->filled('remember');

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
                'not_regex:/(.)\1{2,}/',
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
            'password' => Hash::make(Str::random(24)),
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

    private function generateUniqueUsername(string $name): string
    {
        $time_start_username_gen = microtime(true);
        $baseUsername = Str::slug($name, '');
        if (empty($baseUsername)) {
            $baseUsername = 'user';
        }
        if (!preg_match('/^[a-zA-Z]/', $baseUsername)) {
            $baseUsername = 'u' . $baseUsername;
        }
        $baseUsername = substr($baseUsername, 0, 20);


        $cacheKey = 'username_check_' . md5($baseUsername);
        if (Cache::has($cacheKey)) {
            $cachedUsername = Cache::get($cacheKey);
            if (!User::where('username', $cachedUsername)->exists()) {
                Log::channel('audit_trail')->debug('Using cached unique username.', ['username' => $cachedUsername]);
                return $cachedUsername;
            } else {
                Cache::forget($cacheKey);
            }
        }

        $username = $baseUsername;
        $counter = 1;
        $maxLength = 24;

        if (strlen($username) > $maxLength) {
            $username = substr($username, 0, $maxLength);
        }

        $maxAttempts = 1000;
        $attempt = 0;
        while (User::where('username', $username)->exists()) {
            if ($attempt++ >= $maxAttempts) {
                $username = 'user' . Str::random(10);
                while (User::where('username', $username)->exists()) {
                    $username = 'user' . Str::random(10);
                }
                Log::channel('audit_trail')->warning('Max attempts reached for username generation, used random.', ['base' => $baseUsername, 'final_username' => $username]);
                break;
            }

            $suffix = (string)$counter;
            $availableLengthForBase = $maxLength - strlen($suffix);

            if ($availableLengthForBase < 1) { // Suffix itself is too long, should not happen with small counters
                $username = Str::random($maxLength - 5) . Str::random(5); // Highly defensive
                continue;
            }

            if (strlen($baseUsername) > $availableLengthForBase) {
                $username = substr($baseUsername, 0, $availableLengthForBase) . $suffix;
            } else {
                $username = $baseUsername . $suffix;
            }
            $counter++;
        }

        Cache::put($cacheKey, $username, now()->addMinutes(10)); // Cache the successfully generated username
        $duration_username_gen = microtime(true) - $time_start_username_gen;
        Log::channel('audit_trail')->info('Generated unique username.', [
            'original_name' => $name,
            'base_username' => $baseUsername,
            'generated_username' => $username,
            'attempts' => $attempt,
            'duration_seconds' => $duration_username_gen
        ]);
        return $username;
    }
}

