<?php

namespace App\Console\Commands;

use App\Mail\RegistrationExpired;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CleanupUnverifiedUsers extends Command
{
    protected $signature = 'users:cleanup-unverified';
    protected $description = 'Remove users who have not verified their email within 1 hour';

    public function handle()
    {
        $this->info('Starting cleanup of unverified users...');

        // Find users created more than 1 hour ago who haven't verified their email
        $users = User::whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHour())
            ->get();

        $count = $users->count();
        $this->info("Found {$count} unverified users to remove");

        foreach ($users as $user) {
            try {
                // Send notification email before deleting
                $this->sendExpirationNotification($user);

                // Log the removal
                Log::info("Removing unverified user: {$user->email} (ID: {$user->id})");

                // Delete the user
                $user->delete();

                $this->info("Removed user: {$user->email}");
            } catch (\Exception $e) {
                $this->error("Error processing user {$user->email}: {$e->getMessage()}");
                Log::error("Failed to process unverified user {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info('Unverified users cleanup completed');
        return Command::SUCCESS;
    }

    private function sendExpirationNotification(User $user)
    {
        Mail::to($user->email)->send(new RegistrationExpired($user));
    }
}
