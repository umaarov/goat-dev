<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    final public function showRegistrationForm(): View
    {
        // Add debugging information
        Log::info('Session ID in registration form: ' . Session::getId());
        Log::info('CSRF Token in registration form: ' . Session::token());

        return view('auth.register');
    }

    final public function register(Request $request): RedirectResponse
    {
        // Add debugging information
        Log::info('Register - Session ID: ' . $request->session()->getId());
        Log::info('Register - CSRF Token: ' . $request->session()->token());
        Log::info('Register - Submitted CSRF: ' . $request->input('_token'));

        // Validate form input
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $profilePicturePath = null;
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');
        }

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'profile_picture' => $profilePicturePath,
            ]);

            Auth::login($user);

            // Force regenerate the session
            $request->session()->regenerate();

            return redirect()->route('home')->with('success', 'Registration successful! Welcome!');
        } catch (Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return redirect()->back()
                ->withErrors(['error' => 'Registration failed. Please try again.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }

    final public function showLoginForm(): View
    {
        // Add debugging information
        Log::info('Session ID in login form: ' . Session::getId());
        Log::info('CSRF Token in login form: ' . Session::token());

        return view('auth.login');
    }

    final public function login(Request $request): RedirectResponse
    {
        // Add debugging information
        Log::info('Login - Session ID: ' . $request->session()->getId());
        Log::info('Login - CSRF Token: ' . $request->session()->token());
        Log::info('Login - Submitted CSRF: ' . $request->input('_token'));

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
            // Force regenerate the session to prevent session fixation attacks
            $request->session()->regenerate();

            Log::info('Login successful for email: ' . $request->email);
            return redirect()->intended(route('home'))->with('success', 'Logged in successfully!');
        }

        Log::warning('Failed login attempt for email: ' . $request->email);
        return redirect()->back()
            ->withErrors(['email' => 'Invalid login credentials.'])
            ->withInput($request->only('email'));
    }

    final public function logout(Request $request): RedirectResponse
    {
        // Add debugging information
        Log::info('Logout - Session ID: ' . $request->session()->getId());

        Auth::logout();

        // Invalidate and regenerate the session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Logged out successfully!');
    }

    final public function googleRedirect()
    {
        // Add debugging information
        Log::info('Google redirect - Session ID: ' . Session::getId());

        try {
            return Socialite::driver('google')->redirect();
        } catch (Exception $e) {
            Log::error('Google redirect failed: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }
    }

    final public function googleCallback(Request $request): RedirectResponse
    {
        // Add debugging information
        Log::info('Google callback - Session ID: ' . $request->session()->getId());

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            Log::info('Google user retrieved', ['email' => $googleUser->getEmail()]);

            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $existingUser = User::where('email', $googleUser->getEmail())->first();

                if ($existingUser) {
                    $existingUser->google_id = $googleUser->getId();
                    if (!$existingUser->profile_picture && $googleUser->getAvatar()) {
                        $existingUser->profile_picture = $googleUser->getAvatar();
                    }
                    $existingUser->save();
                    $user = $existingUser;
                    Log::info('Updated existing user with Google ID', ['email' => $user->email]);
                } else {
                    $username = $this->generateUniqueUsername($googleUser->getName());

                    $name = $googleUser->getName();
                    $nameParts = explode(' ', $name);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

                    // Try to get given_name and family_name if available
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
                        'profile_picture' => $googleUser->getAvatar(),
                        'email_verified_at' => now(),
                        'password' => Hash::make(Str::random(24)), // Create a random password for security
                    ]);
                    Log::info('Created new user from Google', ['email' => $user->email]);
                }
            } else {
                Log::info('Found existing Google user', ['email' => $user->email]);
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
                Log::error("Could not generate a unique username for '{$name}' after 1000 attempts.");
                return 'user' . Str::random(10);
            }
        }

        return $username;
    }
}
