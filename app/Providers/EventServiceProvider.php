<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendWelcomeMessage;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendWelcomeMessage::class,
        ],
    ];
}
