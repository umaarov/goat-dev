<?php

namespace App\Console\Commands;

use App\Jobs\SendPostDigestEmail;
use App\Models\NotificationSchedule;
use Illuminate\Console\Command;

class ProcessNotificationSchedules extends Command
{
    protected $signature = 'app:process-notification-schedules';
    protected $description = 'Process the notification schedule and dispatch email jobs for due users.';

    public function handle()
    {
        $schedules = NotificationSchedule::where('send_at', '<=', now())->get();

        if ($schedules->isEmpty()) {
            $this->info('No notifications are due to be sent.');
            return;
        }

        $this->info("Found {$schedules->count()} notifications to process.");

        foreach ($schedules as $schedule) {
            SendPostDigestEmail::dispatch($schedule->user);
            $schedule->delete();
        }

        $this->info('All due notifications have been queued.');
    }
}
