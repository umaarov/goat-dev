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
        Log::debug('SetLocale Middleware: Configured session.secure = ' . var_export(config('session.secure'), true));
        Log::debug('SetLocale Middleware: Configured session.same_site = ' . var_export(config('session.same_site'), true));
        Log::debug('==================================================');
        Log::debug('SetLocale Middleware: Execution started for URL: ' . $request->fullUrl());
        Log::debug('SetLocale Middleware: Session Driver: ' . Config::get('session.driver'));
        Log::debug('SetLocale Middleware: Session ID: ' . Session::getId());
        Log::debug('SetLocale Middleware: ALL SESSION DATA AT START: ', Session::all());

        $availableLocalesConfig = Config::get('app.available_locales');
        $availableLocaleKeys = is_array($availableLocalesConfig) ? array_keys($availableLocalesConfig) : ['en'];
        // app.locale is overwritten by App::setLocale(); the real default must not leak between requests in a long-lived worker
        $defaultLocaleConfig = Config::get('app.default_locale', 'en');

        $sessionLocaleValue = Session::get('locale');
        $user = Auth::user();

        Log::debug('SetLocale Middleware: Initial state check', [
            'is_user_authenticated' => !is_null($user),
            'user_id_if_auth' => $user ? $user->id : 'N/A',
            'user_locale_from_db_if_auth' => $user ? $user->locale : 'N/A',
            'session_has_locale_key' => Session::has('locale'),
            'session_locale_value_retrieved' => $sessionLocaleValue,
            'config_default_locale' => $defaultLocaleConfig,
            'config_available_locales' => $availableLocaleKeys,
            'browser_accept_language' => $request->header('Accept-Language'),
            'current_app_locale_before_set' => App::getLocale(),
        ]);

        $localeToSet = null;
        $sourceOfLocale = 'None yet';

        // Priority: 1. User DB (if user is available), 2. Session, 3. Browser, 4. Config Default

        // Check 1: User's Database Preference
        $requested = $request->query('lang');
        if (is_string($requested) && in_array($requested, $availableLocaleKeys, true)) {
            $localeToSet = $requested;
            $sourceOfLocale = 'URL ?lang=';
            if ($request->hasSession()) {
                Session::put('locale', $requested);
            }
        } elseif ($user && property_exists($user, 'locale') && $user->locale && in_array($user->locale, $availableLocaleKeys)) {
            $localeToSet = $user->locale;
            $sourceOfLocale = 'User DB Preference';
            Log::debug('SetLocale Middleware: Decision Step 1 - Using user preference from DB: "' . $localeToSet . '"');
        } // Check 2: Session Preference (if not set by user or user not available)
        elseif ($sessionLocaleValue && in_array($sessionLocaleValue, $availableLocaleKeys)) {
            $localeToSet = $sessionLocaleValue;
            $sourceOfLocale = 'Session Preference';
            Log::debug('SetLocale Middleware: Decision Step 2 - Using session preference: "' . $localeToSet . '"');
        } // Check 3: Browser Preference
        elseif ($request->hasHeader('Accept-Language')) {
            $preferredLocale = $request->getPreferredLanguage($availableLocaleKeys);
            if ($preferredLocale) {
                $localeToSet = $preferredLocale;
                $sourceOfLocale = 'Browser Accept-Language';
                Log::debug('SetLocale Middleware: Decision Step 3 - Using browser preference: "' . $localeToSet . '"');
            } else {
                Log::debug('SetLocale Middleware: Info - Browser preferred language not in available locales list (Step 3).');
            }
        }

        // Check 4: Fallback to application's default locale from config
        if (!$localeToSet || !in_array($localeToSet, $availableLocaleKeys)) {
            $originalAttempt = $localeToSet;
            $localeToSet = $defaultLocaleConfig;
            if ($sourceOfLocale === 'None yet' || ($originalAttempt && !in_array($originalAttempt, $availableLocaleKeys))) {
                $sourceOfLocale = 'Config Default Fallback';
            }
            Log::debug('SetLocale Middleware: Decision Step 4 - Using default/fallback locale: "' . $localeToSet . '" (Original attempt if any: "' . $originalAttempt . '", Initial Source: ' . $sourceOfLocale . ')');
        }

        Log::debug('SetLocale Middleware: Final Chosen locale: "' . $localeToSet . '" from Source: ' . $sourceOfLocale);

        App::setLocale($localeToSet);
        $finalAppLocale = App::getLocale();
        Log::debug('SetLocale Middleware: App::setLocale("' . $localeToSet . '") called. App::getLocale() is now: "' . $finalAppLocale . '".');

        if ($localeToSet !== $finalAppLocale) {
            Log::error('SetLocale Middleware: CRITICAL - Mismatch! Attempted to set "' . $localeToSet . '" but App::getLocale() reports "' . $finalAppLocale . '".');
        }

        view()->share('current_locale', $finalAppLocale);
        view()->share('available_locales', $availableLocalesConfig ?: ['en' => 'English']);
        Log::debug('SetLocale Middleware: Shared "current_locale" (' . $finalAppLocale . ') with views.');
        Log::debug('==================================================');

        $response = $next($request);
        // the page differs by language header and cookie: say so, in case anything ever caches it
        $response->headers->set('Vary', implode(', ', array_unique(array_filter(array_merge(
            array_map('trim', explode(',', (string) $response->headers->get('Vary'))),
            ['Accept-Language', 'Cookie']
        )))));

        return $response;
    }
}
