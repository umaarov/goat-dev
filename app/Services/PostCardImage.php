<?php

namespace App\Services;

use App\Models\Post;
use GdImage;
use RuntimeException;

// 1200x630 JPEG preview of a question: both options side by side, the question on top, the live split at the bottom
class PostCardImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const MAX_SOURCE_PIXELS = 36_000_000;

    // changes whenever anything shown on the card changes, so it doubles as the cache-busting version
    public function version(Post $post): string
    {
        return substr(sha1(implode('|', [
            'v1', $post->question, $post->option_one_title, $post->option_two_title,
            $post->option_one_image, $post->option_two_image,
            $post->option_one_votes, $post->option_two_votes, $post->total_votes,
        ])), 0, 10);
    }

    public function render(Post $post): string
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 17, 17, 20));

        $this->panel($canvas, $post->option_one_image, 0, [37, 99, 235]);
        $this->panel($canvas, $post->option_two_image, self::WIDTH / 2, [220, 38, 38]);
        $this->shade($canvas);

        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $white = imagecolorallocate($canvas, 255, 255, 255);

        // question, centred on top
        $lines = $this->wrap($this->clean($post->question, 150), $font, 46, self::WIDTH - 140, 3);
        $y = 78;
        foreach ($lines as $line) {
            $this->centered($canvas, $line, $font, 46, self::WIDTH / 2, $y, $white, true);
            $y += 62;
        }

        // options and the split
        $total = max(0, (int) $post->total_votes);
        $one = $total > 0 ? (int) round($post->option_one_votes / $total * 100) : null;
        $two = $one === null ? null : 100 - $one;
        $gold = imagecolorallocate($canvas, 250, 204, 21);

        foreach ([[$post->option_one_title, $one, 300, $one !== null && $one >= $two], [$post->option_two_title, $two, 900, $two !== null && $two > $one]] as [$title, $percent, $x, $leads]) {
            $this->centered($canvas, $this->wrap($this->clean($title, 40), $font, 34, 520, 1)[0] ?? '', $font, 34, $x, 520, $white, true);
            $this->centered($canvas, $percent === null ? '?' : "{$percent}%", $font, 78, $x, 440, $leads ? $gold : $white, true);
        }
        $caption = $total > 0 ? number_format($total).' votes · goat.uz' : 'Be the first to vote · goat.uz';
        $this->centered($canvas, $caption, $font, 24, self::WIDTH / 2, 596, imagecolorallocate($canvas, 209, 213, 219), true);

        // VS badge
        imagefilledellipse($canvas, self::WIDTH / 2, 330, 104, 104, imagecolorallocate($canvas, 17, 17, 20));
        imagefilledellipse($canvas, self::WIDTH / 2, 330, 92, 92, $white);
        $this->centered($canvas, 'VS', $font, 34, self::WIDTH / 2, 330, imagecolorallocate($canvas, 17, 17, 20), true);

        // JPEG: a photo card is ~100 KB instead of ~700 KB (WhatsApp ignores previews over ~300 KB)
        imageinterlace($canvas, true);
        ob_start();
        imagejpeg($canvas, null, 84);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return $jpeg;
    }

    // option picture covering its half; a plain colour when the file is missing or unusable
    private function panel(GdImage $canvas, ?string $path, int $x, array $fallback): void
    {
        $half = self::WIDTH / 2;
        $source = $this->load($path);

        if (!$source) {
            imagefilledrectangle($canvas, $x, 0, $x + $half - 1, self::HEIGHT - 1, imagecolorallocate($canvas, ...$fallback));

            return;
        }

        $w = imagesx($source);
        $h = imagesy($source);
        $scale = max($half / $w, self::HEIGHT / $h);
        $cropW = (int) round($half / $scale);
        $cropH = (int) round(self::HEIGHT / $scale);
        imagecopyresampled($canvas, $source, $x, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), (int) $half, self::HEIGHT, $cropW, $cropH);
        imagedestroy($source);
    }

    private function load(?string $path): ?GdImage
    {
        if (!$path) {
            return null;
        }
        $file = rtrim((string) config('filesystems.disks.public.root'), '/').'/'.ltrim($path, '/');
        $real = realpath($file);
        $root = realpath((string) config('filesystems.disks.public.root'));
        // only files that really live in the uploads directory
        if (!$real || !$root || !str_starts_with($real, $root.DIRECTORY_SEPARATOR) || !is_file($real)) {
            return null;
        }

        $info = @getimagesize($real);
        if (!$info || $info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($real),
            IMAGETYPE_PNG => @imagecreatefrompng($real),
            IMAGETYPE_WEBP => @imagecreatefromwebp($real),
            IMAGETYPE_GIF => @imagecreatefromgif($real),
            default => false,
        };

        return $image ?: null;
    }

    // darken the top (question) and the bottom (numbers) so white text stays readable on any photo
    private function shade(GdImage $canvas): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $top = max(0, 1 - $y / 250);
            $bottom = max(0, ($y - 330) / 300);
            $alpha = (int) round(127 - 127 * min(0.9, max($top * 0.88, $bottom * 0.92)));
            imageline($canvas, 0, $y, self::WIDTH, $y, imagecolorallocatealpha($canvas, 8, 8, 12, $alpha));
        }
    }

    private function centered(GdImage $canvas, string $text, string $font, int $size, int|float $cx, int $y, int $color, bool $middle = false): void
    {
        if ($text === '') {
            return;
        }
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $baseline = $middle ? $y + (int) round($size * 0.36) : $y;
        imagettftext($canvas, $size, 0, (int) round($cx - $width / 2 - $box[0]), $baseline, $color, $font, $text);
    }

    // word wrap to a pixel width, at most $maxLines, ending with an ellipsis when cut
    private function wrap(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $width = fn (string $s) => ($b = imagettfbbox($size, 0, $font, $s)) ? $b[2] - $b[0] : 0;
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $try = $line === '' ? $word : "{$line} {$word}";
            if ($width($try) <= $maxWidth) {
                $line = $try;

                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;
            // a single word wider than the card
            while ($width($line) > $maxWidth && mb_strlen($line) > 1) {
                $line = mb_substr($line, 0, -1);
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];
            while ($width($last.'…') > $maxWidth && mb_strlen($last) > 1) {
                $last = mb_substr($last, 0, -1);
            }
            $lines[$maxLines - 1] = rtrim($last).'…';
        }

        return $lines;
    }

    // plain single-line text: no control characters, bounded length
    private function clean(?string $text, int $limit): string
    {
        $text = preg_replace('/[\p{C}]+/u', ' ', (string) $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)).'…' : $text;
    }
}
