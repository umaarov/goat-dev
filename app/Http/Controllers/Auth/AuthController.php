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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    final public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    final public function register(Request $request): RedirectResponse
    {
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

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_picture' => $profilePicturePath,
        ]);

        Auth::login($user);

        return redirect()->route('home')->with('success', 'Registration successful! Welcome!');
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
            $request->session()->regenerate();
            return redirect()->intended(route('home'))->with('success', 'Logged in successfully!');
        }

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

    final public function googleRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    final public function googleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    $existingUser->google_id = $googleUser->id;
                    if (!$existingUser->profile_picture && $googleUser->avatar) {
                        $existingUser->profile_picture = $googleUser->avatar;
                    }
                    $existingUser->save();
                    $user = $existingUser;
                } else {
                    $username = $this->generateUniqueUsername($googleUser->name);

                    $user = User::create([
                        'first_name' => $googleUser->user['given_name'] ?? explode(' ', $googleUser->name)[0],
                        'last_name' => $googleUser->user['family_name'] ?? (count(explode(' ', $googleUser->name)) > 1 ? implode(' ', array_slice(explode(' ', $googleUser->name), 1)) : null),
                        'username' => $username,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'profile_picture' => $googleUser->avatar,
                        'email_verified_at' => now(),
                        'password' => null,
                    ]);
                }
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('home'))->with('success', 'Logged in with Google successfully!');

        } catch (Exception $e) {
            Log::error('Google Auth Failed: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Unable to login using Google. Please try again.');
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
