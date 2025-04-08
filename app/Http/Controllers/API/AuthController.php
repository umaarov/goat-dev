<?php

namespace App\Http\Controllers\Auth; // Changed Namespace

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite; // Keep if using Socialite

class AuthController extends Controller
{
    /**
     * Show the registration form.
     */
    final function showRegistrationForm(): View
    {
        return view('auth.register'); // Assumes view exists at resources/views/auth/register.blade.php
    }

    /**
     * Handle a registration request for the application.
     */
    final function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // Add password confirmation
            'profile_picture' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation')); // Don't flash passwords
        }

        $profilePicturePath = null;
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_picture' => $profilePicturePath,
        ]);

        Auth::login($user); // Log the user in using session

        // Redirect to a dashboard or home page after registration
        return redirect()->route('dashboard')->with('success', 'Registration successful!'); // Assumes a route named 'dashboard'
    }

    /**
     * Show the login form.
     */
    final function showLoginForm(): View
    {
        return view('auth.login'); // Assumes view exists at resources/views/auth/login.blade.php
    }

    /**
     * Handle a login request to the application.
     */
    final function login(Request $request): RedirectResponse
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
        $remember = $request->filled('remember'); // Check if remember me is checked

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate(); // Regenerate session ID for security
            // Redirect to intended page or dashboard
            return redirect()->intended(route('dashboard'))->with('success', 'Logged in successfully!');
        }

        // If login fails
        return redirect()->back()
            ->withErrors(['email' => 'Invalid login credentials.']) // General error message
            ->withInput($request->only('email'));
    }

    /**
     * Log the user out of the application.
     */
    final function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate(); // Invalidate the session
        $request->session()->regenerateToken(); // Regenerate CSRF token

        // Redirect to home page after logout
        return redirect('/')->with('success', 'Logged out successfully!');
    }

    /**
     * Redirect the user to the Google authentication page.
     */
    final function googleRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    final function googleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    // Link Google ID to existing email account
                    $existingUser->google_id = $googleUser->id;
                    // Optionally update profile picture if missing or desired
                    if (!$existingUser->profile_picture && $googleUser->avatar) {
                        $existingUser->profile_picture = $googleUser->avatar;
                    }
                    $existingUser->save();
                    $user = $existingUser;
                } else {
                    // Create a new user
                    $username = $this->generateUniqueUsername($googleUser->name);

                    $user = User::create([
                        'first_name' => $googleUser->user['given_name'] ?? explode(' ', $googleUser->name)[0],
                        'last_name' => $googleUser->user['family_name'] ?? (count(explode(' ', $googleUser->name)) > 1 ? explode(' ', $googleUser->name)[1] : null),
                        'username' => $username,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'profile_picture' => $googleUser->avatar,
                        'email_verified_at' => now(), // Mark email as verified
                        // Password can be null for social logins, or set a random one if required
                        // 'password' => Hash::make(Str::random(16)),
                    ]);
                }
            }

            Auth::login($user, true); // Log the user in (true enables remember me)
            $request->session()->regenerate();

            // Redirect to intended page or dashboard
            return redirect()->intended(route('dashboard'))->with('success', 'Logged in with Google successfully!');

        } catch (Exception $e) {
            // Log the error: \Log::error('Google Auth Failed: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Unable to login using Google. Please try again.');
        }
    }

    /**
     * Generate a unique username from a given name.
     */
    private function generateUniqueUsername(string $name): string
    {
        $baseUsername = Str::slug(strtolower($name));
        if (empty($baseUsername)) {
            $baseUsername = 'user'; // Fallback if name is empty or yields empty slug
        }
        $username = $baseUsername;
        $counter = 1;

        // Ensure username doesn't exceed max length (e.g., 255) during generation
        $maxLength = 255 - (strlen((string)$counter) + 1); // Max length for base part

        while (User::where('username', $username)->exists()) {
            // Shorten base if needed to accommodate counter
            $baseUsernameCurrent = substr($baseUsername, 0, $maxLength);
            $username = $baseUsernameCurrent . $counter;
            $counter++;
            $maxLength = 255 - (strlen((string)$counter) + 1);
            // Add a safety break for extremely unlikely infinite loops
            if ($counter > 1000) {
                throw new Exception("Could not generate a unique username for '{$name}'");
            }
        }

        return $username;
    }
}
