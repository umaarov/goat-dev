<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeMessage implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct()
    {
        //
    }

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeMessage($event->user));
    }
}
