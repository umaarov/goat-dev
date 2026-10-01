<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramAuthService
{
    private const MAX_AGE_SECONDS = 300;

    protected string $botToken;

    public function __construct()
    {
        $this->botToken = (string) config('services.telegram.bot_token');
    }

    public function validate(array $authData): ?array
    {
        $checkHash = $authData['hash'] ?? null;

        if (empty($this->botToken) || !is_string($checkHash) || $checkHash === '') {
            Log::error('Telegram Auth Failed: Bot token or hash is missing.');
            return null;
        }

        unset($authData['hash']);
        ksort($authData);

        $dataCheckString = collect($authData)
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        $secretKey = hash('sha256', $this->botToken, true);
        $expected = hash_hmac('sha256', $dataCheckString, $secretKey);

        // never log $expected or the data string: they are enough to forge a login
        if (!hash_equals($expected, strtolower($checkHash))) {
            Log::warning('Telegram Auth Failed: Invalid hash.');
            return null;
        }

        $authDate = (int) ($authData['auth_date'] ?? 0);
        if ($authDate <= 0 || abs(time() - $authDate) > self::MAX_AGE_SECONDS) {
            Log::warning('Telegram Auth Failed: Stale or missing auth_date.');
            return null;
        }

        // a signed payload is single use
        if (!Cache::add('tg_auth:' . $expected, 1, self::MAX_AGE_SECONDS * 2)) {
            Log::warning('Telegram Auth Failed: Replayed payload.');
            return null;
        }

        return $authData;
    }
}
