<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

// header-only check, before any decoder allocates memory for a hostile image
final class ImageGuard
{
    public const MAX_SIDE = 12000;
    public const MAX_PIXELS = 40_000_000;

    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];

    public static function assertSafe(string $path, string $field = 'image'): void
    {
        $info = @getimagesize($path);

        if ($info === false || !in_array($info[2], self::ALLOWED, true)) {
            throw ValidationException::withMessages([$field => 'The file is not a supported image.']);
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1
            || $width > self::MAX_SIDE || $height > self::MAX_SIDE
            || $width * $height > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => 'The image dimensions are too large.']);
        }
    }
}
