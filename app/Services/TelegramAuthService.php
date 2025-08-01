<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelegramAuthService
{
    protected string $botToken;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
    }

    public function validate(array $authData): ?array
    {
        if (empty($this->botToken) || !isset($authData['hash'])) {
            Log::error('Telegram Auth Failed: Bot token or hash is missing from auth data.');
            return null;
        }

        $checkHash = $authData['hash'];
        unset($authData['hash']);
        ksort($authData);

        $dataCheckString = collect($authData)
            ->map(fn($value, $key) => "$key=$value")
            ->implode("\n");

        $secretKey = hash('sha256', $this->botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $authDate = (int)($authData['auth_date'] ?? 0);
        if (time() - $authDate > 300) {
            Log::warning('Telegram Auth Failed: Stale auth_date.', [
                'auth_date' => $authDate,
                'current_time' => time(),
                'difference_seconds' => time() - $authDate
            ]);
            return null;
        }

        if (hash_equals($hash, $checkHash)) {
            return $authData;
        }

        Log::warning('Telegram Auth Failed: Invalid hash.', [
            'data_check_string' => $dataCheckString,
            'expected_hash' => $hash,
            'received_hash' => $checkHash,
        ]);
        return null;
    }
}
