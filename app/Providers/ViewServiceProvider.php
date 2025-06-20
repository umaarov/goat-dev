<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $unreadCount = 0;
            if (Auth::check()) {
                $unreadCount = Auth::user()->unreadNotifications()->count();
            }
            $view->with('unreadNotificationsCount', $unreadCount);
        });
    }
}
