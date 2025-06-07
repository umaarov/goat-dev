<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    final function register(): void
    {
//        $this->app->register(EventServiceProvider::class);
    }

    final function boot(): void
    {
        //
    }
}
