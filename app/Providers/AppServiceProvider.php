<?php

namespace App\Providers;

use App\Extensions\SafeFailedJobProvider;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use App\Http\Middleware\EnforceSessionRevocation;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    final function register(): void
    {
        $this->app->singleton('files', function () {
            return new Filesystem();
        });
        $this->app->extend('queue.failed', function ($service, $app) {
            return new SafeFailedJobProvider(
                $app['config']->get('queue.failed.database'),
                $app['config']->get('queue.failed.table')
            );
        });
    }

    final function boot(): void
    {
        // every web login (password, social, refresh cookie) stamps its session
        Event::listen(Login::class, function () {
            if (request()->hasSession()) {
                request()->session()->put(EnforceSessionRevocation::KEY, time());
            }
        });

        Password::defaults(function () {
            $rule = Password::min((int) config('security.password.min_length', 10))->max(128);

            return config('security.password.check_breached') ? $rule->uncompromised(3) : $rule;
        });

        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });
    }
}
