<?php

namespace App\Services\Backup;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class TelegramBackupSender
{
    private const DEFAULT_API = 'https://api.telegram.org';

    private const ATTEMPTS = 4;

    // why nothing may be sent, or null when sending is allowed
    public function refusal(): ?string
    {
        if (!config('backup.telegram.enabled')) {
            return 'BACKUP_TELEGRAM_ENABLED is off';
        }
        if (!$this->token() || !$this->chat()) {
            return 'BACKUP_TELEGRAM_BOT_TOKEN or BACKUP_TELEGRAM_CHAT_ID is not set';
        }
        // never reach the real Telegram from a dev or test machine
        if (!app()->isProduction() && $this->api() === self::DEFAULT_API) {
            return 'not production (real Telegram is only used in production)';
        }

        return null;
    }

    public function document(string $path, string $name, string $caption, bool $silent = true): void
    {
        $this->call('sendDocument', ['caption' => $caption, 'disable_notification' => $silent ? 'true' : 'false'], [$path, $name]);
    }

    public function message(string $text, bool $silent = false): void
    {
        $this->call('sendMessage', ['text' => $text, 'disable_notification' => $silent ? 'true' : 'false']);
    }

    public function scrub(string $text): string
    {
        return $this->token() ? str_replace((string) $this->token(), '***', $text) : $text;
    }

    private function call(string $method, array $fields, ?array $file = null): void
    {
        $fields['chat_id'] = $this->chat();
        $last = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                $response = $this->request($file)->post("{$this->api()}/bot{$this->token()}/{$method}", $fields);
            } catch (ConnectionException $e) {
                $last = $this->scrub($e->getMessage());
                Sleep::for(min(2 ** $attempt, 30))->seconds();

                continue;
            }

            if ($response->json('ok') === true) {
                return;
            }

            $last = $this->describe($response);

            // flood control: Telegram says how long to wait; other 4xx answers will not improve
            if ($response->status() === 429) {
                Sleep::for(min((int) $response->json('parameters.retry_after', 5) + 1, 90))->seconds();
            } elseif ($response->serverError()) {
                Sleep::for(min(2 ** $attempt, 30))->seconds();
            } else {
                break;
            }
        }

        throw new RuntimeException("Telegram {$method} failed: {$last}");
    }

    private function request(?array $file): PendingRequest
    {
        $request = Http::timeout(600)->connectTimeout(15);

        if ($file) {
            $handle = fopen($file[0], 'rb');
            if ($handle === false) {
                throw new RuntimeException("cannot read {$file[0]}");
            }
            $request = $request->attach('document', $handle, $file[1]);
        }

        return $request;
    }

    private function describe(Response $response): string
    {
        $text = $response->json('description') ?: ('HTTP '.$response->status());

        return $this->scrub("{$response->status()} {$text}");
    }

    private function token(): ?string
    {
        return config('backup.telegram.token');
    }

    private function chat(): ?string
    {
        return config('backup.telegram.chat_id');
    }

    private function api(): string
    {
        return config('backup.telegram.api_url');
    }
}
