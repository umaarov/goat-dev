<?php

namespace App\Support;

use Illuminate\Http\Request;
use Spatie\ResponseCache\CacheProfiles\CacheAllSuccessfulGetRequests;

// the page text depends on the language, so each language is its own cache entry (per user when signed in)
class LocaleAwareCacheProfile extends CacheAllSuccessfulGetRequests
{
    public function useCacheNameSuffix(Request $request): string
    {
        return parent::useCacheNameSuffix($request).'|'.app()->getLocale();
    }
}
