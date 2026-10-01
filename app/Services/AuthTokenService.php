<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthTokenService
{
    private int $tokenLifetimeDays;
    private int $refreshWithinHours;
    private int $gracePeriodSeconds;

    public function __construct()
    {
        $this->tokenLifetimeDays = (int) config('auth.refresh_token_lifetime', 90);
        $this->refreshWithinHours = (int) config('auth.refresh_within_hours', 12);
        $this->gracePeriodSeconds = (int) config('security.refresh.grace_seconds', 10);
    }

    // __Host- pins the cookie to this exact host: no Domain, Path=/, Secure
    public static function cookieName(): string
    {
        return config('session.secure') ? '__Host-refresh_token' : 'refresh_token';
    }

    public function revokeAllTokensForUser(User $user): void
    {
        $user->revokeAllCredentials();
    }

    public function validateAndExtendSession(Request $request): ?SymfonyCookie
    {
        $refreshToken = $request->cookie(self::cookieName());

        if (!$refreshToken) {
            return null;
        }

        $tokenModel = $this->getValidToken($refreshToken);

        if (!$tokenModel) {
            return $this->clearCookie();
        }

        return $this->refreshTokenIfNeeded($refreshToken, $request);
    }

    public function getValidToken(string $plainTextToken): ?RefreshToken
    {
        if (empty($plainTextToken)) {
            return null;
        }

        $hashedToken = hash('sha256', $plainTextToken);

        $token = RefreshToken::with('user')
            ->where('token', $hashedToken)
            ->first();

        if (!$token) {
            return null;
        }

        if ($token->revoked_at) {
            if ($token->grace_period_ends_at && $token->grace_period_ends_at->isFuture()) {
                return $token;
            }

            Log::channel('audit_trail')->warning('[AUTH] [TOKEN_THEFT] Revoked token used outside grace period. Revoking all sessions.', [
                'user_id' => $token->user_id,
                'token_id' => $token->id
            ]);

            $this->revokeAllTokensForUser($token->user);
            return null;
        }

        if ($token->expires_at->isPast()) {
            $this->revokeToken($token);
            return null;
        }

        return $token;
    }

    // null when another request already rotated this token
    public function rotateToken(RefreshToken $oldToken, Request $request): ?SymfonyCookie
    {
        if (!$this->claim($oldToken)) {
            return null;
        }

        return $this->issueToken($oldToken->user, $request);
    }

    public function revokeToken(RefreshToken $token): void
    {
        $token->update(['revoked_at' => now()]);

        Log::channel('audit_trail')->info('[AUTH] [TOKEN] Refresh token revoked', [
            'token_id' => $token->id,
            'user_id' => $token->user_id,
        ]);
    }

    public function clearCookie(): SymfonyCookie
    {
        return Cookie::make(
            self::cookieName(),
            null,
            -2628000,
            '/',
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            'Lax'
        );
    }

    public function refreshTokenIfNeeded(string $plainTextToken, Request $request): ?SymfonyCookie
    {
        $tokenModel = $this->getValidToken($plainTextToken);

        if (!$tokenModel) {
            return null;
        }

        if ($tokenModel->expires_at->diffInHours(now()) <= $this->refreshWithinHours) {
            return $this->rotateToken($tokenModel, $request);
        }

        return null;
    }

    public function issueToken(User $user, Request $request): SymfonyCookie
    {
        $plainTextToken = Str::random(64);
        $hashedToken = hash('sha256', $plainTextToken);

        RefreshToken::where('user_id', $user->id)
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = RefreshToken::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => now()->addDays($this->tokenLifetimeDays),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Log::channel('audit_trail')->info('[AUTH] [TOKEN] New Refresh token issued', [
            'user_id' => $user->id,
            'token_id' => $token->id
        ]);

        return $this->createCookie($plainTextToken);
    }

    public function createCookie(string $plainTextToken): SymfonyCookie
    {
        return Cookie::make(
            self::cookieName(),
            $plainTextToken,
            $this->tokenLifetimeDays * 24 * 60,
            '/',
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            'Lax'
        );
    }

    public function shouldRotate(RefreshToken $token): bool
    {
        return $token->created_at->diffInHours(now()) >= $this->refreshWithinHours;
    }

    // atomic: only one concurrent request can win the rotation
    private function claim(RefreshToken $token): bool
    {
        return RefreshToken::whereKey($token->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'grace_period_ends_at' => now()->addSeconds($this->gracePeriodSeconds),
                ]) === 1;
    }
}
