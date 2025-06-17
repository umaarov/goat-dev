<?php

namespace App\Providers;

use Illuminate\Filesystem\Filesystem;
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
    }
}
