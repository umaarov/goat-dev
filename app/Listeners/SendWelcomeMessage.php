<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeMessage;
use Exception;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWelcomeMessage
{
    use InteractsWithQueue;

    public function __construct()
    {
        //
    }

    public function handle(UserRegistered $event): void
    {
        Log::info('SendWelcomeMessage listener handled for user: ' . $event->user->email);

        try {
            Mail::to($event->user->email)->send(new WelcomeMessage($event->user));
            Log::info('Welcome email dispatched successfully for: ' . $event->user->email);
        } catch (Exception $e) {
            Log::error('Failed to send welcome email for ' . $event->user->email . '. Error: ' . $e->getMessage());
        }
    }
}
