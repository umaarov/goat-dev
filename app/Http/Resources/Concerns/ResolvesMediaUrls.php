<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ResolvesMediaUrls
{
    /**
     * Resolve a stored media reference into an absolute, client-usable URL.
     *
     * Handles three storage conventions used across the app:
     *  - absolute URLs (e.g. social-provider avatars) -> returned as-is
     *  - data URIs (e.g. LQIP placeholders)            -> returned as-is
     *  - public-disk relative paths                    -> converted to a full URL
     */
    protected function mediaUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'data:'])) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
