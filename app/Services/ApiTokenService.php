<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Issues and rotates authentication tokens for the mobile API.
 *
 * - Access token: a short-lived Sanctum personal access token (Bearer).
 * - Refresh token: a long-lived opaque token persisted (hashed) in the
 *   existing `refresh_tokens` table and returned in JSON (no cookies).
 *
 * Mirrors the rotation / grace-period / reuse-detection semantics of the
 * web AuthTokenService, but speaks JSON instead of Set-Cookie.
 */
class ApiTokenService
{
    private int $accessTokenMinutes;

    private int $refreshTokenDays;

    private int $gracePeriodSeconds = 60;

    public function __construct()
    {
        $this->accessTokenMinutes = (int) config('auth.api_access_token_minutes', 60);
        $this->refreshTokenDays = (int) config('auth.refresh_token_lifetime', 90);
    }

    /**
     * Issue a brand-new access + refresh token pair for a user.
     *
     * @return array{access_token:string,refresh_token:string,token_type:string,expires_in:int}
     */
    public function issueTokens(User $user, Request $request, string $deviceName = 'mobile'): array
    {
        $expiresAt = now()->addMinutes($this->accessTokenMinutes);

        $access = $user->createToken($deviceName, ['*'], $expiresAt);

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $this->issueRefreshToken($user, $request),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenMinutes * 60,
        ];
    }

    /**
     * Exchange a valid refresh token for a fresh token pair (rotation).
     *
     * Returns null when the token is missing / expired / reused outside the
     * grace window (in which case all of the user's refresh tokens are revoked
     * as a theft-mitigation measure, matching AuthTokenService).
     */
    public function refresh(string $plainRefreshToken, Request $request): ?array
    {
        $token = $this->findToken($plainRefreshToken);

        if (! $token) {
            return null;
        }

        if ($token->revoked_at) {
            // Reuse of an already-rotated token outside the grace period =>
            // assume token theft and nuke the whole family.
            if (! $token->grace_period_ends_at || $token->grace_period_ends_at->isPast()) {
                Log::channel('audit_trail')->warning('[API] [TOKEN_THEFT] Revoked refresh token reused. Revoking all sessions.', [
                    'user_id' => $token->user_id,
                    'token_id' => $token->id,
                ]);
                $token->user->refreshTokens()->update(['revoked_at' => now()]);

                return null;
            }
        }

        if ($token->expires_at->isPast()) {
            $token->update(['revoked_at' => now()]);

            return null;
        }

        // Rotate: revoke the old token with a short grace window, issue a new pair.
        $token->update([
            'revoked_at' => now(),
            'grace_period_ends_at' => now()->addSeconds($this->gracePeriodSeconds),
        ]);

        return $this->issueTokens($token->user, $request);
    }

    /**
     * Revoke a single refresh token (used on logout).
     */
    public function revokeRefreshToken(?string $plainRefreshToken): void
    {
        if (empty($plainRefreshToken)) {
            return;
        }

        RefreshToken::where('token', hash('sha256', $plainRefreshToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Revoke every refresh token belonging to a user (logout from all devices).
     */
    public function revokeAllRefreshTokens(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * Active refresh-token "sessions" for the given user.
     */
    public function activeSessions(User $user)
    {
        return $user->refreshTokens()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();
    }

    private function issueRefreshToken(User $user, Request $request): string
    {
        $plainTextToken = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays($this->refreshTokenDays),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $plainTextToken;
    }

    private function findToken(string $plainTextToken): ?RefreshToken
    {
        if (empty($plainTextToken)) {
            return null;
        }

        return RefreshToken::with('user')
            ->where('token', hash('sha256', $plainTextToken))
            ->first();
    }
}
