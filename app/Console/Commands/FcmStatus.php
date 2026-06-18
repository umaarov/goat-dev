<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Console\Command;

class FcmStatus extends Command
{
    protected $signature = 'fcm:status';

    protected $description = 'Show the Firebase Cloud Messaging (push) configuration status';

    public function handle(FcmService $fcm): int
    {
        $configured = $fcm->isConfigured();

        $this->components->twoColumnDetail('FCM configured', $configured ? '<fg=green>yes</>' : '<fg=red>no</>');
        $this->components->twoColumnDetail('Project ID', config('services.fcm.project_id') ?: '<fg=red>(not set)</>');
        $this->components->twoColumnDetail('Credentials path', config('services.fcm.credentials') ?: '<fg=red>(not set)</>');

        try {
            $deviceCount = (string) DeviceToken::count();
        } catch (\Throwable $e) {
            $deviceCount = '<fg=yellow>n/a (db unreachable)</>';
        }
        $this->components->twoColumnDetail('Registered devices', $deviceCount);

        if (! $configured) {
            $this->newLine();
            $this->components->warn('Push is disabled. Set FCM_PROJECT_ID and place the service-account JSON at the configured path, then run `php artisan config:clear`.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Push is ready. Test it with: php artisan fcm:test {token} (or --user=ID)');

        return self::SUCCESS;
    }
}
