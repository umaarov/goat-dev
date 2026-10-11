<?php

namespace App\Support;

class Fonts
{
    public static function bold(): string
    {
        return self::pick('Inter-Bold.ttf');
    }

    public static function black(): string
    {
        return self::pick('Inter-Black.ttf');
    }

    private static function pick(string $inter): string
    {
        $path = storage_path('app/fonts/'.$inter);

        return is_file($path) ? $path : resource_path('fonts/DejaVuSans-Bold.ttf');
    }
}
