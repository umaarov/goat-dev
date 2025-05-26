<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerificationService;
use Exception;
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
            return Socialite::driver('google')->redirect();
        } catch (Exception $e) {
            Log::error('Google redirect failed: ' . $e->getMessage());
            return redirect()->route('login')->with('error', __('messages.error_google_auth_failed'));
        }
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $userModel = User::where('google_id', $googleUser->getId())->first();
            $action = "Logged in";

            if (!$userModel) {
                $userModel = $this->handleGoogleUser($googleUser);
                $action = 'Registered and logged in';
            }
            $user = $userModel;


            Auth::login($user, true);
            $request->session()->regenerate();

            Log::channel('audit_trail')->info("User $action via Google.", [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'google_id' => $googleUser->getId(),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->intended(route('home'))->with('success', __('messages.google_login_success'));

        } catch (Exception $e) {
            Log::channel('audit_trail')->error('Google authentication/callback failed.', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
            ]);
            Log::error('Google Auth System Error', [
                'error' => $e->getMessage(),
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
        $existingUser = User::where('email', $googleUser->getEmail())->first();

        if ($existingUser) {
            return $this->updateExistingUserWithGoogle($existingUser, $googleUser);
        } else {
            return $this->createUserFromGoogle($googleUser);
        }
    }

    private function updateExistingUserWithGoogle(User $user, $googleUser): User
    {
        $user->google_id = $googleUser->getId();

        if (!$user->profile_picture && $googleUser->getAvatar()) {
            $user->profile_picture = $googleUser->getAvatar();
        }

        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        return $user;
    }

    private function createUserFromGoogle($googleUser): User
    {
        $username = $this->generateUniqueUsername($googleUser->getName());

        $name = $googleUser->getName();
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? null;

        if (isset($googleUser->user['given_name'])) {
            $firstName = $googleUser->user['given_name'];
        }

        if (isset($googleUser->user['family_name'])) {
            $lastName = $googleUser->user['family_name'];
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(24)),
        ]);

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

        return $user;
    }

    private function generateUniqueUsername(string $name): string
    {
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


        while (User::where('username', $username)->exists()) {
            $suffix = (string)$counter;
            $availableLength = $maxLength - strlen($suffix);

            if (strlen($baseUsername) > $availableLength) {
                $username = substr($baseUsername, 0, $availableLength) . $suffix;
            } else {
                $username = $baseUsername . $suffix;
            }
            $counter++;

            if ($counter > 1000) {
                $username = 'user' . Str::random(10);
                while (User::where('username', $username)->exists()) {
                    $username = 'user' . Str::random(10);
                }
                break;
            }
        }

        Cache::put($cacheKey, $username, now()->addMinutes(10));

        return $username;
    }
}
