<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\Messages\FcmMessage;
use App\Services\FcmService;
use Illuminate\Console\Command;

class FcmTest extends Command
{
    protected $signature = 'fcm:test
        {token? : A raw FCM device token to send to}
        {--user= : Send to every registered device of this user id}
        {--title=GOAT : Notification title}
        {--body=Test push from GOAT 🐐 : Notification body}';

    protected $description = 'Send a test push notification via FCM';

    public function handle(FcmService $fcm): int
    {
        if (! $fcm->isConfigured()) {
            $this->components->error('FCM is not configured. Run `php artisan fcm:status`.');

            return self::FAILURE;
        }

        $tokens = $this->resolveTokens();

        if ($tokens->isEmpty()) {
            $this->components->error('No device tokens to send to. Pass a {token} or --user=ID with registered devices.');

            return self::FAILURE;
        }

        $message = new FcmMessage(
            title: (string) $this->option('title'),
            body: (string) $this->option('body'),
            data: ['type' => 'test'],
        );

        $ok = 0;
        foreach ($tokens as $token => $model) {
            $result = $fcm->send($token, $message);
            $this->components->twoColumnDetail(
                substr($token, 0, 24).'…',
                match ($result) {
                    'ok' => '<fg=green>sent</>',
                    'invalid' => '<fg=yellow>invalid (pruned)</>',
                    default => "<fg=red>{$result}</>",
                }
            );

            if ($result === 'ok') {
                $ok++;
            } elseif ($result === 'invalid' && $model instanceof DeviceToken) {
                $model->delete();
            }
        }

        $this->newLine();
        $this->components->info("Done: {$ok}/{$tokens->count()} delivered.");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<string, DeviceToken|null>
     */
    private function resolveTokens(): \Illuminate\Support\Collection
    {
        if ($userId = $this->option('user')) {
            $user = User::find($userId);
            if (! $user) {
                $this->components->error("User {$userId} not found.");

                return collect();
            }

            return $user->deviceTokens->keyBy('token')->map(fn ($d) => $d);
        }

        if ($raw = $this->argument('token')) {
            $model = DeviceToken::where('token', $raw)->first();

            return collect([$raw => $model]);
        }

        return collect();
    }
}
