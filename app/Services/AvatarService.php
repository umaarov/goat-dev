<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class AvatarService
{
    final public function generateInitialsAvatar(string $firstName, string $lastName, string $userId, int $size = 200): string
    {
        $firstInitial = mb_substr($firstName, 0, 1);
        $lastInitial = !empty($lastName) ? mb_substr($lastName, 0, 1) : '';
        $initials = mb_strtoupper($firstInitial . $lastInitial);

        $hash = md5($userId);
        $hue = hexdec(substr($hash, 0, 2)) % 360;

        $img = Image::canvas($size, $size, $this->hsvToRgb($hue, 0.7, 0.9));

        $img->text($initials, $size / 2, $size / 2, function ($font) use ($size) {
            $font->file(public_path('fonts/poppins.ttf'));
            $font->size($size * 0.4);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('middle');
        });

        $path = 'profile_pictures/initial_' . $userId . '.png';
        Storage::disk('public')->put($path, $img->encode('png'));

        return $path;
    }

    private function hsvToRgb($h, $s, $v): string
    {
        $h_i = floor($h / 60) % 6;
        $f = $h / 60 - $h_i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        switch ($h_i) {
            case 0:
                [$r, $g, $b] = [$v, $t, $p];
                break;
            case 1:
                [$r, $g, $b] = [$q, $v, $p];
                break;
            case 2:
                [$r, $g, $b] = [$p, $v, $t];
                break;
            case 3:
                [$r, $g, $b] = [$p, $q, $v];
                break;
            case 4:
                [$r, $g, $b] = [$t, $p, $v];
                break;
            case 5:
                [$r, $g, $b] = [$v, $p, $q];
                break;
        }

        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);

        return "rgba($r, $g, $b, 1)";
    }
}
