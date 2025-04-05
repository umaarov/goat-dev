<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
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

        return $this->createTokenAndCookies($user);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid login credentials'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        return $this->createTokenAndCookies($user);
    }

    public function logout(Request $request)
    {
        // Revoke current access token
        $request->user()->currentAccessToken()->delete();

        // Revoke refresh token from cookie if it exists
        if ($request->hasCookie('refresh_token')) {
            $tokenValue = $request->cookie('refresh_token');
            $refreshToken = RefreshToken::where('token', $tokenValue)->first();

            if ($refreshToken) {
                $refreshToken->revoked = true;
                $refreshToken->save();
            }
        }

        // Clear cookies
        $cookie = Cookie::forget('refresh_token');

        return response()->json(['message' => 'Logged out successfully'])->withCookie($cookie);
    }

    public function refresh(Request $request)
    {
        if (!$request->hasCookie('refresh_token')) {
            return response()->json(['message' => 'Refresh token not found'], 401);
        }

        $tokenValue = $request->cookie('refresh_token');
        $refreshToken = RefreshToken::where('token', $tokenValue)
            ->where('revoked', false)
            ->first();

        if (!$refreshToken || $refreshToken->isExpired()) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $user = $refreshToken->user;

        // Revoke old refresh token
        $refreshToken->revoked = true;
        $refreshToken->save();

        // Create new tokens and cookies
        return $this->createTokenAndCookies($user);
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)->first();

            if (!$user) {
                // Check if email exists
                $existingUser = User::where('email', $googleUser->email)->first();

                if ($existingUser) {
                    // Link Google account to existing user
                    $existingUser->google_id = $googleUser->id;
                    $existingUser->save();
                    $user = $existingUser;
                } else {
                    // Create new user
                    $username = $this->generateUniqueUsername($googleUser->name);

                    $user = User::create([
                        'first_name' => $googleUser->user['given_name'] ?? explode(' ', $googleUser->name)[0],
                        'last_name' => $googleUser->user['family_name'] ?? (count(explode(' ', $googleUser->name)) > 1 ? explode(' ', $googleUser->name)[1] : null),
                        'username' => $username,
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                        'profile_picture' => $googleUser->avatar,
                        'email_verified_at' => now(),
                    ]);
                }
            }

            // Generate response with tokens and cookies
            $response = $this->createTokenAndCookies($user);

            // Redirect to frontend with the access token
            return redirect(config('app.frontend_url') . '?auth=success');

        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '?auth=failed');
        }
    }

    public function user(Request $request)
    {
        return response()->json($request->user()->load([
            'posts' => function ($query) {
                $query->latest()->take(5);
            },
            'savedPosts' => function ($query) {
                $query->latest()->take(5);
            },
            'votedPosts' => function ($query) {
                $query->latest()->take(5);
            }
        ]));
    }

    private function createTokenAndCookies(User $user)
    {
        // Revoke any existing tokens for this user
        $user->tokens()->delete();

        // Create new access token
        $token = $user->createToken('api-token', ['*'], Carbon::now()->addMinutes(60));

        // Create new refresh token
        $refreshToken = Str::random(60);
        $user->refreshTokens()->create([
            'token' => $refreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        // Create HTTP-only cookie for refresh token
        $cookie = cookie(
            'refresh_token',
            $refreshToken,
            43200, // 30 days in minutes
            '/',
            null,
            true,
            true,
            false,
            'none'
        );

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60, // 1 hour in seconds
            'user' => $user,
        ])->withCookie($cookie);
    }

    private function generateUniqueUsername($name)
    {
        $baseUsername = Str::slug(strtolower($name));
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }
}
