<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('==================================================');
        Log::info('SetLocale Middleware: Execution started for URL: ' . $request->fullUrl());

        $availableLocalesConfig = Config::get('app.available_locales');
        $availableLocaleKeys = is_array($availableLocalesConfig) ? array_keys($availableLocalesConfig) : ['en'];
        $defaultLocaleConfig = Config::get('app.locale', 'en');
        $sessionLocale = Session::get('locale');
        $user = Auth::user();

        Log::info('SetLocale Middleware: Initial state check', [
            'is_user_authenticated' => !is_null($user),
            'user_id_if_auth' => $user ? $user->id : 'N/A',
            'user_locale_from_db_if_auth' => $user ? $user->locale : 'N/A',
            'session_has_locale_key' => Session::has('locale'),
            'session_locale_value' => $sessionLocale,
            'config_default_locale' => $defaultLocaleConfig,
            'config_available_locales' => $availableLocaleKeys,
            'browser_accept_language' => $request->header('Accept-Language'),
            'current_app_locale_before_set' => App::getLocale(),
        ]);

        $localeToSet = null;
        $sourceOfLocale = 'None yet';

        // 1. User's preference (if authenticated and user object is available)
        if ($user && property_exists($user, 'locale') && $user->locale && in_array($user->locale, $availableLocaleKeys)) {
            $localeToSet = $user->locale;
            $sourceOfLocale = 'User DB Preference';
            Log::info('SetLocale Middleware: Decision - Using user preference from DB: "' . $localeToSet . '"');
        }

        // 2. Session preference (if no user preference OR user not available yet)
        if (!$localeToSet && $sessionLocale && in_array($sessionLocale, $availableLocaleKeys)) {
            $localeToSet = $sessionLocale;
            $sourceOfLocale = 'Session Preference';
            Log::info('SetLocale Middleware: Decision - Using session preference: "' . $localeToSet . '"');
        }

        // 3. Browser preference (Accept-Language header) if no other preference found
        if (!$localeToSet && $request->hasHeader('Accept-Language')) {
            $preferredLocale = $request->getPreferredLanguage($availableLocaleKeys);
            if ($preferredLocale) {
                $localeToSet = $preferredLocale;
                $sourceOfLocale = 'Browser Accept-Language';
                Log::info('SetLocale Middleware: Decision - Using browser preference: "' . $localeToSet . '"');
            } else {
                Log::info('SetLocale Middleware: Info - Browser preferred language not in available locales list.');
            }
        }

        // 4. Fallback to application's default locale from config
        if (!$localeToSet || !in_array($localeToSet, $availableLocaleKeys)) {
            $originalAttempt = $localeToSet;
            $localeToSet = $defaultLocaleConfig;
            $sourceOfLocale = 'Config Default Fallback';
            Log::info('SetLocale Middleware: Decision - Falling back to config default locale: "' . $localeToSet . '" (Original attempt: "' . $originalAttempt . '")');
        }

        App::setLocale($localeToSet);
        $finalAppLocale = App::getLocale();
        Log::info('SetLocale Middleware: Final - App::setLocale("' . $localeToSet . '") called. App::getLocale() is now: "' . $finalAppLocale . '". Source: ' . $sourceOfLocale);

        if ($localeToSet !== $finalAppLocale) {
            Log::error('SetLocale Middleware: CRITICAL - Mismatch! Attempted to set "' . $localeToSet . '" but App::getLocale() reports "' . $finalAppLocale . '".');
        }

        view()->share('current_locale', $finalAppLocale);
        view()->share('available_locales', $availableLocalesConfig ?: ['en' => 'English']);
        Log::info('SetLocale Middleware: Shared "current_locale" (' . $finalAppLocale . ') and "available_locales" with views.');
        Log::info('==================================================');

        return $next($request);
    }
}
