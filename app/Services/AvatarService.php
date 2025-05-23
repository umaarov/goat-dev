<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class AvatarService
{
    final public function generateInitialsAvatar(string $firstName, string $lastName, string $userId, int $size = 200): string
    {
        $firstInitial = mb_substr($firstName, 0, 1);
        $lastInitial = !empty($lastName) ? mb_substr($lastName, 0, 1) : '';
        $initials = mb_strtoupper($firstInitial . $lastInitial);

        $manager = new ImageManager(new GdDriver());

        $hash = md5($userId);
        $hue = hexdec(substr($hash, 0, 2)) % 360;
        $backgroundColor = $this->hsvToRgb($hue, 0.7, 0.9);

        $image = $manager->create($size, $size)->fill($backgroundColor);


        $image->text($initials, $size / 2, $size / 2, function (FontFactory $font) use ($size) {
            $font->file(public_path('fonts/poppins.ttf'));
            $font->size($size * 0.4);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('middle');
        });

        $path = 'profile_pictures/initial_' . $userId . '.png';

        // $encodedImage = $image->toPng()->toString();

        $encodedImage = $image->toPng(['compression' => 6])->toString();


        Storage::disk('public')->put($path, $encodedImage);

        return $path;
    }

    private function hsvToRgb(float $h, float $s, float $v): string
    {
        $h_i = floor($h / 60) % 6;
        $f = $h / 60 - $h_i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        $r_float = 0.0;
        $g_float = 0.0;
        $b_float = 0.0;

        switch ($h_i) {
            case 0:
                [$r_float, $g_float, $b_float] = [$v, $t, $p];
                break;
            case 1:
                [$r_float, $g_float, $b_float] = [$q, $v, $p];
                break;
            case 2:
                [$r_float, $g_float, $b_float] = [$p, $v, $t];
                break;
            case 3:
                [$r_float, $g_float, $b_float] = [$p, $q, $v];
                break;
            case 4:
                [$r_float, $g_float, $b_float] = [$t, $p, $v];
                break;
            case 5:
            default:
                [$r_float, $g_float, $b_float] = [$v, $p, $q];
                break;
        }

        $r = round($r_float * 255);
        $g = round($g_float * 255);
        $b = round($b_float * 255);

        return "rgb($r, $g, $b)";
    }
}
