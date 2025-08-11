<?php

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\NotificationSchedule;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SchedulePostNotificationsListener implements ShouldQueue
{
    public function handle(PostCreated $event): void
    {
//        $subscribedUserIds = User::where('receives_notifications', true)
//            ->pluck('id');
//        $alreadyScheduledUserIds = NotificationSchedule::whereIn('user_id', $subscribedUserIds)
//            ->pluck('user_id');

        $subscribedUserIds = User::where('email', 'hs.umarov21@gmail.com')->pluck('id');

        $alreadyScheduledUserIds = NotificationSchedule::whereIn('user_id', $subscribedUserIds)
            ->pluck('user_id');

        $usersToSchedule = $subscribedUserIds->diff($alreadyScheduledUserIds);

        $schedules = [];
        foreach ($usersToSchedule as $userId) {
//            $randomDelayInSeconds = rand(1800, 43200);
            $randomDelayInSeconds = rand(5, 15);

            $schedules[] = [
                'user_id' => $userId,
                'send_at' => now()->addSeconds($randomDelayInSeconds),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($schedules)) {
            NotificationSchedule::insert($schedules);
        }
    }
}
