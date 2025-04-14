<?php

namespace App\Console\Commands;

use App\Mail\RegistrationExpired;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CleanupUnverifiedUsers extends Command
{
    protected $signature = 'users:cleanup-unverified';
    protected $description = 'Remove users who have not verified their email within 1 hour';

    final public function handle(): int
    {
        $this->info('Starting cleanup of unverified users...');

        $users = User::whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHour())
            ->get();

        $count = $users->count();
        $this->info("Found {$count} unverified users to remove");

        foreach ($users as $user) {
            try {
                $this->sendExpirationNotification($user);

                Log::info("Removing unverified user: {$user->email} (ID: {$user->id})");

                $user->delete();

                $this->info("Removed user: {$user->email}");
            } catch (Exception $e) {
                $this->error("Error processing user {$user->email}: {$e->getMessage()}");
                Log::error("Failed to process unverified user {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info('Unverified users cleanup completed');
        return CommandAlias::SUCCESS;
    }

    final public function sendExpirationNotification(User $user): void
    {
        Mail::to($user->email)->send(new RegistrationExpired($user));
    }
}
