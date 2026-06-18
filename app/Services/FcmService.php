<?php

namespace App\Services;

use App\Notifications\Messages\FcmMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications via the Firebase Cloud Messaging HTTP v1 API.
 *
 * Authentication uses a Google service-account credential (reusing the
 * google/apiclient dependency already present) to mint a short-lived OAuth2
 * access token, which is cached. When FCM is not configured the service is a
 * safe no-op, so notifications never fail in local/test environments.
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    private ?string $projectId;

    private ?string $credentialsPath;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->credentialsPath = $this->resolvePath(config('services.fcm.credentials'));
    }

    /**
     * Resolve a configured credentials path: absolute paths are used as-is,
     * relative paths (e.g. "storage/app/service-account.json") are resolved
     * against the application base path so they work regardless of CWD.
     */
    private function resolvePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $isAbsolute = (bool) preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\)#', $path);

        return $isAbsolute ? $path : base_path($path);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->projectId)
            && ! empty($this->credentialsPath)
            && is_readable($this->credentialsPath);
    }

    /**
     * Send one message to one device token.
     *
     * @return string one of: ok | invalid | error | skipped
     *                "invalid" means the token is no longer registered and
     *                should be removed by the caller.
     */
    public function send(string $deviceToken, FcmMessage $message): string
    {
        if (! $this->isConfigured()) {
            return 'skipped';
        }

        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return 'error';
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $message->title,
                            'body' => $message->body,
                        ],
                        'data' => array_map(static fn ($v) => (string) $v, $message->data),
                    ],
                ]);

            if ($response->successful()) {
                return 'ok';
            }

            // 404 / UNREGISTERED / invalid argument for the token => prune it.
            $status = $response->status();
            $fcmStatus = $response->json('error.status');
            if ($status === 404 || in_array($fcmStatus, ['NOT_FOUND', 'UNREGISTERED'], true)) {
                return 'invalid';
            }

            Log::warning('FcmService: send failed', ['status' => $status, 'body' => $response->body()]);

            return 'error';
        } catch (\Throwable $e) {
            Log::error('FcmService: exception sending push: '.$e->getMessage());

            return 'error';
        }
    }

    private function accessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            try {
                $client = new \Google\Client;
                $client->setAuthConfig($this->credentialsPath);
                $client->addScope(self::SCOPE);
                $token = $client->fetchAccessTokenWithAssertion();

                return $token['access_token'] ?? null;
            } catch (\Throwable $e) {
                Log::error('FcmService: failed to obtain access token: '.$e->getMessage());

                return null;
            }
        });
    }
}
