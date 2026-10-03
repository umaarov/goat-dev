<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterval;

class MailText
{
    // Carbon's plain "uz" is Cyrillic; the site writes Uzbek in Latin
    public static function carbonLocale(): string
    {
        return app()->getLocale() === 'uz' ? 'uz_Latn' : app()->getLocale();
    }

    // "1 hour", "1 час", "1 soat": for "this link works for ..."
    public static function duration(int $minutes): string
    {
        return CarbonInterval::minutes($minutes)->cascade()->locale(self::carbonLocale())->forHumans(['parts' => 1, 'short' => false]);
    }

    public static function name(User $user): string
    {
        return $user->first_name ?: $user->username;
    }
}
