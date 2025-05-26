<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class LocaleController extends Controller
{
    public function setLocale(Request $request, $locale)
    {
        $availableLocalesConfig = Config::get('app.available_locales');
        $availableLocaleKeys = is_array($availableLocalesConfig) ? array_keys($availableLocalesConfig) : ['en'];

        if (!in_array($locale, $availableLocaleKeys)) {
            Log::warning("LocaleController: Attempt to set invalid locale '{$locale}'.", [
                'ip' => $request->ip(),
                'available_locales' => $availableLocaleKeys
            ]);

            $errorMessage = __('messages.error_invalid_locale_selected', ['locale' => $locale]);
            if ($errorMessage === 'messages.error_invalid_locale_selected') {
                $errorMessage = "The selected language \"{$locale}\" is not valid.";
            }
            return Redirect::back()->with('error', $errorMessage);
        }

        Session::put('locale', $locale);
        Log::info("LocaleController: Session locale explicitly set to '{$locale}'.", ['session_id' => Session::getId()]);

        if (Auth::check()) {
            $user = Auth::user();
            if (property_exists($user, 'locale') && $user->locale !== $locale) {
                try {
                    $user->locale = $locale;
                    $user->save();
                    Log::info("LocaleController: User DB locale updated for user_id:{$user->id} to '{$locale}'.");
                } catch (Exception $e) {
                    Log::error("LocaleController: Failed to update user DB locale for user_id:{$user->id}.", ['error' => $e->getMessage()]);
                }
            }
        }

        $previousUrl = URL::previous();
        $intendedTarget = route('home');

        if ($previousUrl && !str_contains($previousUrl, route('language.set', ['locale' => 'any'], false))) {
            $intendedTarget = $previousUrl;
        }

        Log::info("LocaleController: Redirecting to '{$intendedTarget}' after locale change to '{$locale}'.");
        return Redirect::to($intendedTarget);
    }
}
