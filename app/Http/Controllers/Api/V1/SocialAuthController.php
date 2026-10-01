<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserRegistered;
use App\Http\Requests\Api\V1\SocialLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\ApiTokenService;
use App\Services\SocialAccountService;
use App\Services\SocialUserData;
use App\Services\TelegramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Native (token-based) social authentication for mobile clients.
 *
 * The mobile app authenticates with the provider's native SDK and posts the
 * resulting credential here; we verify it server-side, then resolve it to a
 * local account and issue our own API tokens.
 */
class SocialAuthController extends ApiController
{
    private const PROVIDERS = ['google', 'x', 'telegram', 'github'];

    public function __construct(
        private ApiTokenService $tokens,
        private SocialAccountService $socialAccounts,
        private TelegramAuthService $telegramAuth,
    ) {}

    /**
     * POST /auth/social/{provider}
     */
    public function login(SocialLoginRequest $request, string $provider): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return $this->error('Unsupported provider.', 404, 'not_found');
        }

        $data = $this->verify($provider, $request);
        if (! $data) {
            return $this->error("Could not verify the {$provider} credential.", 401, 'social_verification_failed');
        }

        ['user' => $user, 'created' => $created] = $this->socialAccounts->resolve($provider, $data);

        if ($created) {
            event(new UserRegistered($user));
        }

        $tokens = $this->tokens->issueTokens($user, $request, $request->input('device_name', 'mobile'));

        Log::channel('audit_trail')->info('[API] [SOCIAL] Social login.', [
            'user_id' => $user->id,
            'provider' => $provider,
            'created' => $created,
        ]);

        return $this->ok(array_merge($tokens, [
            'user' => (new UserResource($user))->resolve($request),
        ]));
    }

    /**
     * POST /me/social/{provider}  (authenticated) — link a provider.
     */
    public function link(SocialLoginRequest $request, string $provider): JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return $this->error('Unsupported provider.', 404, 'not_found');
        }

        $data = $this->verify($provider, $request);
        if (! $data) {
            return $this->error("Could not verify the {$provider} credential.", 401, 'social_verification_failed');
        }

        $result = $this->socialAccounts->link($request->user(), $provider, $data);

        if (! $result['linked']) {
            return $this->error($result['message'], 409, 'social_link_conflict');
        }

        return $this->ok([
            'message' => $result['message'],
            'user' => (new UserResource($request->user()->fresh()))->resolve($request),
        ]);
    }

    /**
     * Verify a provider credential and normalise it. Returns null on failure.
     */
    private function verify(string $provider, Request $request): ?SocialUserData
    {
        try {
            return match ($provider) {
                'google' => $this->verifyGoogle($request->input('token')),
                'telegram' => $this->verifyTelegram($request->input('telegram', [])),
                'x' => $this->verifyX($request),
                'github' => $this->verifyGithub($request->input('token')),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error("[API] [SOCIAL] {$provider} verification error: ".$e->getMessage());

            return null;
        }
    }

    private function verifyGoogle(?string $idToken): ?SocialUserData
    {
        if (! $idToken) {
            return null;
        }

        $client = new \Google\Client(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken($idToken);

        if (! $payload || empty($payload['sub'])) {
            return null;
        }

        return new SocialUserData(
            id: (string) $payload['sub'],
            email: $payload['email'] ?? null,
            name: $payload['name'] ?? trim(($payload['given_name'] ?? '').' '.($payload['family_name'] ?? '')),
            nickname: null,
            avatar: $payload['picture'] ?? null,
            emailVerified: filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    private function verifyTelegram(array $payload): ?SocialUserData
    {
        $data = $this->telegramAuth->validate($payload);

        if (! $data) {
            return null;
        }

        return new SocialUserData(
            id: (string) $data['id'],
            email: null,
            name: trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
            nickname: $data['username'] ?? null,
            avatar: $data['photo_url'] ?? null,
        );
    }

    private function verifyX(Request $request): ?SocialUserData
    {
        $clientId = config('services.x.client_id');
        $clientSecret = config('services.x.client_secret');
        $redirectUri = (string) $request->input('redirect_uri');
        $code = $request->input('code');

        // an access token alone cannot be tied to our app, so only the code exchange is accepted
        if (! $clientId || ! $clientSecret || ! $code || ! $request->filled('code_verifier')) {
            return null;
        }

        if (! in_array($redirectUri, config('services.x.mobile_redirect_uris', []), true)) {
            return null;
        }

        $token = Http::asForm()->withBasicAuth($clientId, $clientSecret)->timeout(10)
            ->post('https://api.twitter.com/2/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $request->input('code_verifier'),
                'client_id' => $clientId,
            ]);

        $accessToken = $token->successful() ? $token->json('access_token') : null;
        if (! $accessToken) {
            return null;
        }

        $response = Http::withToken($accessToken)->timeout(10)
            ->get('https://api.twitter.com/2/users/me', [
                'user.fields' => 'profile_image_url,name,username',
            ]);

        if (! $response->successful() || empty($response->json('data.id'))) {
            return null;
        }

        $u = $response->json('data');

        return new SocialUserData(
            id: (string) $u['id'],
            email: null,
            name: $u['name'] ?? null,
            nickname: $u['username'] ?? null,
            avatar: $u['profile_image_url'] ?? null,
        );
    }

    private function verifyGithub(?string $accessToken): ?SocialUserData
    {
        if (! $accessToken) {
            return null;
        }

        $clientId = config('services.github.client_id');
        $clientSecret = config('services.github.client_secret');

        // refuse to run unconfigured: any app's token would otherwise be accepted
        if (! $clientId || ! $clientSecret || str_starts_with((string) $clientId, 'your_')) {
            return null;
        }

        $gh = fn () => Http::withHeaders(['Accept' => 'application/vnd.github+json'])->timeout(10);

        $bound = $gh()->withBasicAuth($clientId, $clientSecret)
            ->post("https://api.github.com/applications/{$clientId}/token", ['access_token' => $accessToken]);

        if (! $bound->successful() || data_get($bound->json(), 'app.client_id') !== $clientId) {
            return null;
        }

        $response = $gh()->withToken($accessToken)->get('https://api.github.com/user');

        if (! $response->successful() || empty($response->json('id'))) {
            return null;
        }

        $u = $response->json();

        $emails = $gh()->withToken($accessToken)->get('https://api.github.com/user/emails');
        $primary = $emails->successful()
            ? collect($emails->json())->first(fn ($e) => ($e['primary'] ?? false) && ($e['verified'] ?? false))
            : null;

        return new SocialUserData(
            id: (string) $u['id'],
            email: $primary['email'] ?? null,
            name: $u['name'] ?? ($u['login'] ?? null),
            nickname: $u['login'] ?? null,
            avatar: $u['avatar_url'] ?? null,
            emailVerified: $primary !== null,
        );
    }
}
