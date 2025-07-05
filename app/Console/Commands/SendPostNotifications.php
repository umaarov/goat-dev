<?php

namespace App\Console\Commands;

use App\Mail\NewPostsNotification;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPostNotifications extends Command
{
    private const MIN_HOURS_BETWEEN_NOTIFICATIONS = 12;

    private const MIN_POSTS_TO_TRIGGER = 1;

    protected $signature = 'app:send-post-notifications {--test : Send a test email immediately to hs.umarov21@gmail.com}';

    protected $description = 'Sends email notifications to users about new posts with flexible, randomized timing.';

    public function handle()
    {
        if ($this->option('test')) {
            $this->handleTestSend();
            return;
        }

        if (rand(1, 3) !== 1) {
            $this->info('Skipping this run to ensure random timing. No emails sent.');
            return;
        }

        $this->info('Random check passed. Proceeding to check for users and new posts...');
        $this->sendNotificationsToAllUsers();
    }

    private function handleTestSend(): void
    {
        $this->info('Sending an immediate test notification...');
        $testUser = User::where('email', 'hs.umarov21@gmail.com')->first();

        if (!$testUser) {
            $this->error('Test user hs.umarov21@gmail.com not found in the database.');
            return;
        }

        $posts = Post::latest()->take(5)->get();

        if ($posts->count() < 1) {
            $this->warn('No posts found in the database to send as a test.');
            return;
        }

        $this->dispatchEmail($testUser, $posts, false);
    }

    private function dispatchEmail(User $user, $posts, bool $updateTimestamp): void
    {
        $mainPost = $posts->sortByDesc('total_votes')->first();
        $gridPosts = $posts->where('id', '!=', $mainPost->id)->take(4);

        try {
            Mail::to($user)->send(new NewPostsNotification($user, $mainPost, $gridPosts));

            if ($updateTimestamp) {
                $user->last_notified_at = Carbon::now();
                $user->save();
                $this->info("Notification queued successfully for {$user->email} and timestamp updated.");
            } else {
                $this->info("Test notification sent successfully to {$user->email}. Timestamp was not updated.");
            }
        } catch (Exception $e) {
            Log::error("Failed to queue notification email for {$user->email}: " . $e->getMessage());
            $this->error("Failed to send email to {$user->email}. Check logs for details.");
        }
    }

    private function sendNotificationsToAllUsers(): void
    {
        $users = User::where('receives_notifications', true)->get();

        if ($users->isEmpty()) {
            $this->warn('No users are subscribed to notifications. Nothing to do.');
            return;
        }

        $this->info("Found {$users->count()} users to potentially notify.");

        foreach ($users as $user) {
            if ($user->last_notified_at && $user->last_notified_at->gt(Carbon::now()->subHours(self::MIN_HOURS_BETWEEN_NOTIFICATIONS))) {
                $this->line("Skipping user {$user->email}: a notification was sent recently.");
                continue;
            }

            $since = $user->last_notified_at ?? Carbon::now()->subDays(2);
            $newPosts = Post::where('created_at', '>', $since)->latest()->get();

            if ($newPosts->count() < self::MIN_POSTS_TO_TRIGGER) {
                $this->line("Not enough new posts for {$user->email} since {$since->toDateTimeString()}. Found {$newPosts->count()}, need at least " . self::MIN_POSTS_TO_TRIGGER);
                continue;
            }

            $this->dispatchEmail($user, $newPosts, true);
        }

        $this->info('All user notifications processed.');
    }
}
