<?php

namespace App\Console\Commands;

use App\Models\NotificationSchedule;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;

class ScheduleDailyDigests extends Command
{
    private const int DAILY_EMAIL_LIMIT = 60;

    protected $signature = 'app:schedule-daily-digests';
    protected $description = 'Intelligently schedules up to 60 digest emails for the most relevant users.';

    public function handle(): void
    {
        $this->info('Starting Smart Digest Scheduler...');

        $deleted = NotificationSchedule::where('created_at', '<', now()->subDays(2))->delete();
        if ($deleted) {
            $this->info("Cleared $deleted stale schedules.");
        }

        $alreadyScheduledCount = NotificationSchedule::whereDate('send_at', today())->count();
        $slotsAvailable = self::DAILY_EMAIL_LIMIT - $alreadyScheduledCount;

        if ($slotsAvailable <= 0) {
            $this->warn('Daily quota (' . self::DAILY_EMAIL_LIMIT . ') already filled. No new schedules added.');
            return;
        }

        $this->info("Slots available today: $slotsAvailable");

        $candidates = User::where('receives_notifications', true)
            ->whereNotNull('email')
            ->orderBy('last_notified_at', 'asc')
            ->take(200)
            ->get();

        $scheduledCount = 0;
        $scheduleData = [];

        foreach ($candidates as $user) {
            if ($scheduledCount >= $slotsAvailable) {
                break;
            }

            $lastNotified = $user->last_notified_at ?? now()->subDays(7);
            $hasNewPosts = Post::where('created_at', '>', $lastNotified)->exists();

            if (!$hasNewPosts) {
                $user->last_notified_at = now();
                $user->saveQuietly();
                continue;
            }

            $randomHour = rand(9, 20);
            $randomMinute = rand(0, 59);
            $sendAt = now()->setTime($randomHour, $randomMinute, 0);

            if ($sendAt->isPast()) {
                $sendAt = now()->addMinutes(rand(5, 30));
            }

            $scheduleData[] = [
                'user_id' => $user->id,
                'send_at' => $sendAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $scheduledCount++;
        }

        if (!empty($scheduleData)) {
            NotificationSchedule::insert($scheduleData);
            $this->info("Successfully scheduled $scheduledCount smart digests.");
        } else {
            $this->info("No users needed a digest today (or no new content found).");
        }
    }
}
