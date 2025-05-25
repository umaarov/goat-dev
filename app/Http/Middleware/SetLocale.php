<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $availableLocalesConfig = Config::get('app.available_locales');
        $availableLocaleKeys = is_array($availableLocalesConfig) ? array_keys($availableLocalesConfig) : ['en'];
        $defaultLocale = Config::get('app.locale', 'en');
        $sessionLocale = Session::get('locale');
        $user = Auth::user();

        $localeToSet = null;

        // 1. Check for locale in the request (e.g., from a language switcher form that posts to a route)
        // Example: if ($request->has('locale_override') && in_array($request->input('locale_override'), $availableLocaleKeys)) {
        //     $localeToSet = $request->input('locale_override');
        //     Session::put('locale', $localeToSet); // Persist for guest or override
        // }


        // 2. User's preference (if logged in and no override)
        if (!$localeToSet && $user && $user->locale && in_array($user->locale, $availableLocaleKeys)) {
            $localeToSet = $user->locale;
        }

        // 3. Session preference (if no user preference or not logged in, and no override)
        if (!$localeToSet && $sessionLocale && in_array($sessionLocale, $availableLocaleKeys)) {
            $localeToSet = $sessionLocale;
        }

        // 4. Browser preference (Accept-Language header) if no other preference found
        if (!$localeToSet && $request->hasHeader('Accept-Language')) {
            $preferredLocale = $request->getPreferredLanguage($availableLocaleKeys);
            if ($preferredLocale) {
                $localeToSet = $preferredLocale;
            }
        }

        // 5. Fallback to default
        if (!$localeToSet || !in_array($localeToSet, $availableLocaleKeys)) {
            $localeToSet = $defaultLocale;
        }

        App::setLocale($localeToSet);

        view()->share('current_locale', App::getLocale());
        view()->share('available_locales', $availableLocalesConfig ?: ['en' => 'English']);

        return $next($request);
    }
}
