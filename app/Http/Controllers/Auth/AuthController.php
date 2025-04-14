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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    protected AvatarService $avatarService;
    protected EmailVerificationService $emailVerificationService;

    public function __construct(AvatarService $avatarService, EmailVerificationService $emailVerificationService)
    {
        $this->avatarService = $avatarService;
        $this->emailVerificationService = $emailVerificationService;
    }

    final public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    final public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
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
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'profile_picture' => null,
                'email_verification_token' => $this->emailVerificationService->generateToken(),
            ]);

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

            $this->emailVerificationService->sendVerificationEmail($user);

            return redirect()->route('login')
                ->with('success', 'Registration successful! Please check your email to verify your account.');
        } catch (Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return redirect()->back()
                ->withErrors(['error' => 'Registration failed. Please try again.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }

    final public function verifyEmail(Request $request): RedirectResponse
    {
        $user = User::findOrFail($request->id);

        if (!$request->hasValidSignature()) {
            return redirect()->route('verification.notice')
                ->with('error', 'Invalid verification link or the link has expired.');
        }

        if ($this->emailVerificationService->verify($user, $request->token)) {
            if (Auth::check()) {
                return redirect()->route('home')
                    ->with('success', 'Your email has been verified!');
            } else {
                return redirect()->route('login')
                    ->with('success', 'Your email has been verified! You can now log in.');
            }
        }

        return redirect()->route('verification.notice')
            ->with('error', 'Invalid verification token.');
    }

    final public function showVerificationNotice(): View
    {
        return view('auth.verify');
    }

    final public function resendVerificationEmail(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $this->emailVerificationService->sendVerificationEmail($user);

        return back()->with('success', 'Verification link sent! Please check your email.');
    }

    final public function showLoginForm(): View
    {
        return view('auth.login');
    }

    final public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if (!$user->email_verified_at) {
                Auth::logout();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Email not verified. Please check your email for verification link.'])
                    ->withInput($request->only('email'));
            }

            $request->session()->regenerate();
            return redirect()->intended(route('home'))->with('success', 'Logged in successfully!');
        }

        Log::warning('Failed login attempt for email: ' . $request->email);
        return redirect()->back()
            ->withErrors(['email' => 'Invalid login credentials.'])
            ->withInput($request->only('email'));
    }

    final public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Logged out successfully!');
    }

    final public function googleRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse|RedirectResponse
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (Exception) {
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }
    }

    final public function googleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $existingUser = User::where('email', $googleUser->getEmail())->first();

                if ($existingUser) {
                    $existingUser->google_id = $googleUser->getId();
                    if (!$existingUser->profile_picture && $googleUser->getAvatar()) {
                        $existingUser->profile_picture = $googleUser->getAvatar();
                    }
                    $existingUser->email_verified_at = $existingUser->email_verified_at ?? now();
                    $existingUser->save();
                    $user = $existingUser;
                } else {
                    $username = $this->generateUniqueUsername($googleUser->getName());

                    $name = $googleUser->getName();
                    $nameParts = explode(' ', $name);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

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
                    $user->email_verified_at = now();
                    $user->save();
                }
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            return redirect()->intended(route('home'))->with('success', 'Logged in with Google successfully!');

        } catch (Exception $e) {
            Log::error('Google Auth Failed: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Unable to login using Google. Please try again. Error: ' . $e->getMessage());
        }
    }

    private function generateUniqueUsername(string $name): string
    {
        $baseUsername = Str::slug(strtolower($name));
        if (empty($baseUsername)) {
            $baseUsername = 'user';
        }
        $username = $baseUsername;
        $counter = 1;

        $maxLength = 255;

        while (User::where('username', $username)->exists()) {
            $potentialLength = strlen($baseUsername) + strlen((string)$counter) + 1;
            if ($potentialLength > $maxLength) {
                $baseUsername = substr($baseUsername, 0, $maxLength - (strlen((string)$counter) + 1));
            }

            $username = $baseUsername . $counter;
            $counter++;

            if ($counter > 1000) {
                return 'user' . Str::random(10);
            }
        }

        return $username;
    }
}
