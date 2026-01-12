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
    private const COOKIE_NAME = 'refresh_token';
    private int $tokenLifetimeDays;
    private int $refreshWithinHours;
    private int $gracePeriodSeconds = 60;

    public function __construct()
    {
        $this->tokenLifetimeDays = config('auth.refresh_token_lifetime', 90);
        $this->refreshWithinHours = config('auth.refresh_within_hours', 12);
    }

    public function revokeAllTokensForUser(User $user): void
    {
        $user->refreshTokens()->update(['revoked_at' => now()]);
    }

    public function validateAndExtendSession(Request $request): ?SymfonyCookie
    {
        $refreshToken = $request->cookie(self::COOKIE_NAME);

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

    public function rotateToken(RefreshToken $oldToken, Request $request): SymfonyCookie
    {
        $oldToken->update([
            'revoked_at' => now(),
            'grace_period_ends_at' => now()->addSeconds($this->gracePeriodSeconds)
        ]);

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
        $isSecure = config('session.secure', false);
        $sameSite = $isSecure ? 'None' : 'Lax';

        return Cookie::make(
            self::COOKIE_NAME,
            null,
            -2628000,
            '/',
            config('session.domain'),
            $isSecure,
            true,
            false,
            $sameSite
        );
    }

    public function refreshTokenIfNeeded(string $plainTextToken, Request $request): ?SymfonyCookie
    {
        $tokenModel = $this->getValidToken($plainTextToken);

        if (!$tokenModel) {
            return null;
        }

        if ($tokenModel->expires_at->diffInHours(now()) <= $this->refreshWithinHours) {
            $this->revokeToken($tokenModel);
            return $this->issueToken($tokenModel->user, $request);
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
            ->where('id', '!=', $request->input('current_token_id'))
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
        $isSecure = config('session.secure', false);
        $sameSite = $isSecure ? 'None' : 'Lax';

        return Cookie::make(
            self::COOKIE_NAME,
            $plainTextToken,
            $this->tokenLifetimeDays * 24 * 60,
            '/',
            config('session.domain'),
            $isSecure,
            true,
            false,
            $sameSite
        );
    }

    private function revokeTokenFamily(RefreshToken $token): void
    {
        RefreshToken::where('user_id', $token->user_id)
            ->where('ip_address', $token->ip_address)
            ->where('user_agent', $token->user_agent)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function shouldRotate(RefreshToken $token): bool
    {
        return $token->created_at->diffInHours(now()) >= $this->refreshWithinHours;
    }
}
