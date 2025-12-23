<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthTokenService
{
    private const COOKIE_NAME = 'refresh_token';
    private int $tokenLifetimeDays;

    public function __construct()
    {
        $this->tokenLifetimeDays = config('auth.refresh_token_lifetime', 90);
    }

    public function issueToken(User $user, Request $request): SymfonyCookie
    {
        $user->refreshTokens()
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $plainTextToken = Str::random(64);

        $user->refreshTokens()->create([
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays($this->tokenLifetimeDays),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->createCookie($plainTextToken);
    }

    public function createCookie(string $plainTextToken): SymfonyCookie
    {
        return cookie(
            self::COOKIE_NAME,
            $plainTextToken,
            $this->tokenLifetimeDays * 24 * 60,
            null,
            null,
            config('session.secure'),
            true,
            false,
            'Lax'
        );
    }

    public function getValidToken(string $plainTextToken): ?RefreshToken
    {
        $hashedToken = hash('sha256', $plainTextToken);

        $token = RefreshToken::where('token', $hashedToken)->first();

        if (!$token) {
            return null;
        }

        if ($token->revoked_at || $token->expires_at->isPast()) {
            $this->revokeTokenFamily($token);
            return null;
        }

        return $token;
    }

    private function revokeTokenFamily(RefreshToken $token): void
    {
        RefreshToken::where('user_id', $token->user_id)
            // ->where('ip_address', $token->ip_address)
            ->where('user_agent', $token->user_agent)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeToken(RefreshToken $token): void
    {
        $token->update(['revoked_at' => now()]);
    }

    public function revokeAllTokensForUser(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function clearCookie(): SymfonyCookie
    {
        return Cookie::forget(self::COOKIE_NAME);
    }
}
