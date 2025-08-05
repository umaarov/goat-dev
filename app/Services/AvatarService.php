<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class AvatarService
{
    private const AVATAR_SIZE = 200;
    private const FONT_SIZE_RATIO = 0.45;
    private const IMAGE_QUALITY = 85;

    final public function generateInitialsAvatar(?string $firstName, ?string $lastName = '', string $userId): string
    {
        try {
            $firstInitial = !empty($firstName) ? mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8')) : '';
            $lastInitial = !empty($lastName) ? mb_strtoupper(mb_substr($lastName, 0, 1, 'UTF-8')) : '';
            $initials = $firstInitial . $lastInitial;

            if (empty(trim($initials))) {
                $initials = '?';
            }

            $fontPath = storage_path('app/fonts/NotoSans-Regular.ttf');
            if (!file_exists($fontPath)) {
                Log::error('AvatarService: Font not found at path: ' . $fontPath);
                return 'images/avatars/default.png';
            }

            $manager = new ImageManager(new GdDriver());
            $image = $manager->create(self::AVATAR_SIZE, self::AVATAR_SIZE);

            $backgroundColor = $this->generateBackgroundColor($userId);
            $image->fill($backgroundColor);

            $image->text($initials, self::AVATAR_SIZE / 2, self::AVATAR_SIZE / 2, function (FontFactory $font) use ($fontPath) {
                $font->file($fontPath);
                $font->size(self::AVATAR_SIZE * self::FONT_SIZE_RATIO);
                $font->color('#FFFFFF');
                $font->align('center');
                $font->valign('middle');
            });

            $path = 'profile_pictures/initials_' . $userId . '_' . Str::random(5) . '.webp';
            $encodedImage = $image->encode(new WebpEncoder(quality: self::IMAGE_QUALITY));
            Storage::disk('public')->put($path, $encodedImage);

            return $path;

        } catch (Exception $e) {
            Log::error('Failed to generate initials avatar for user ' . $userId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'images/avatars/default.png';
        }
    }

    private function generateBackgroundColor(string $seed): string
    {
        $hash = crc32($seed);
        $hue = $hash % 360;
        $saturation = 0.5;
        $value = 0.8;
        return $this->hsvToRgbString($hue, $saturation, $value);
    }

    private function hsvToRgbString(float $h, float $s, float $v): string
    {
        $h_i = floor($h / 60) % 6;
        $f = $h / 60 - $h_i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);
        switch ($h_i) {
            case 0:
                list($r, $g, $b) = [$v, $t, $p];
                break;
            case 1:
                list($r, $g, $b) = [$q, $v, $p];
                break;
            case 2:
                list($r, $g, $b) = [$p, $v, $t];
                break;
            case 3:
                list($r, $g, $b) = [$p, $q, $v];
                break;
            case 4:
                list($r, $g, $b) = [$t, $p, $v];
                break;
            default:
                list($r, $g, $b) = [$v, $p, $q];
                break;
        }
        return sprintf("#%02x%02x%02x", round($r * 255), round($g * 255), round($b * 255));
    }
}
