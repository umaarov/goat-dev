<?php

namespace App\Providers;

use App\Extensions\SafeFailedJobProvider;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.app', function ($view) {
            $alternateUrls = [];
            $availableLocales = config('app.available_locales', []);

            $currentPath = Request::url();

            $queryParameters = Request::except('lang');

            foreach ($availableLocales as $locale => $language) {
                $queryParameters['lang'] = $locale;

                $alternateUrls[$locale] = $currentPath . '?' . http_build_query($queryParameters);
            }

            $defaultUrl = $currentPath . '?' . http_build_query(Request::except('lang'));

            $view->with('alternateUrls', $alternateUrls)->with('defaultHreflangUrl', $defaultUrl);
        });
        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });
    }
}
