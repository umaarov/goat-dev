<?php

namespace App\Services;

use App\Models\Post;
use Exception;
use GdImage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TelegramService
{
    public function share(Post $post): void
    {
        $post = $post->fresh()->loadMissing('user');

        if (!$post) {
            throw new Exception('Post not found when trying to share to Telegram.');
        }

        $botToken = Config::get('services.telegram.bot_token');
        $chatId = Config::get('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            throw new Exception('Telegram bot token or chat ID is not configured.');
        }

        $imageData = $this->generateTelegramImageWithGd($post);
        if (!$imageData) {
            throw new Exception('Failed to generate the composite image using GD.');
        }

        $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);
        $caption = "⚡️ " . $post->question . "\n\n";
        $caption .= "1️⃣ " . $post->option_one_title . "\n";
        $caption .= "2️⃣ " . $post->option_two_title . "\n\n";
        $caption .= "👉 Vote and see the results:\n" . $postUrl;

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendPhoto";

        $response = Http::timeout(45)->attach(
            'photo',
            $imageData,
            'telegram_post_' . $post->id . '.jpg',
            ['Content-Type' => 'image/jpeg']
        )->post($apiUrl, [
            'chat_id' => $chatId,
            'caption' => $caption,
        ]);

        if (!$response->successful()) {
            Log::error('Telegram API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'post_id' => $post->id,
            ]);
            throw new Exception('Telegram API request failed: ' . $response->body());
        }

        Log::info('Successfully shared post to Telegram', ['post_id' => $post->id]);
    }

    private function generateTelegramImageWithGd(Post $post): ?string
    {
        Log::info('Starting image generation', [
            'post_id' => $post->id,
            'memory_start' => memory_get_usage() / 1024 / 1024 . ' MB',
            'memory_limit' => ini_get('memory_limit'),
        ]);
        ini_set('memory_limit', '1024M');
        $resources = [];

        try {
            $imageOnePath = Storage::disk('public')->path($post->option_one_image);
            $imageTwoPath = Storage::disk('public')->path($post->option_two_image);
            $fontPath = storage_path('app/fonts/Inter-Bold.ttf');
            $logoFontPath = storage_path('app/fonts/Inter-Black.ttf');

            if (!function_exists('imagettftext')) {
                Log::error('GD FreeType support is missing on this server!');
                return null;
            }
            if (!file_exists($fontPath)) {
                Log::error('Font file missing at: ' . $fontPath);
                return null;
            }

            if (!File::exists($imageOnePath) || !File::exists($imageTwoPath) || !File::exists($fontPath) || !File::exists($logoFontPath)) {
                Log::error('Required image or font file not found for GD generation.', ['post_id' => $post->id]);
                return null;
            }

            $canvas = imagecreatetruecolor(1200, 630);
            $resources[] = $canvas;
            $img1_src = imagecreatefromwebp($imageOnePath);
            $img2_src = imagecreatefromwebp($imageTwoPath);
            $resources[] = $img1_src;
            $resources[] = $img2_src;

            imagecopy($canvas, $img1_src, 0, 0, 0, 0, 600, 630);
            imagecopy($canvas, $img2_src, 600, 0, imagesx($img2_src) - 600, 0, 600, 630);

            for ($i = 0; $i < 5; $i++) {
                imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR, 2);
            }

            $overlayColor = imagecolorallocatealpha($canvas, 0, 0, 0, 64);
            imagefilledrectangle($canvas, 0, 0, 1200, 630, $overlayColor);

            $imageSize = 500;
            $borderSize = 8;
            $borderedImageSize = $imageSize + ($borderSize * 2);

            $img1_resized = imagecreatetruecolor($imageSize, $imageSize);
            imagecopyresampled($img1_resized, $img1_src, 0, 0, 0, 0, $imageSize, $imageSize, imagesx($img1_src), imagesy($img1_src));
            $img1_bordered = imagecreatetruecolor($borderedImageSize, $borderedImageSize);
            $borderColor = imagecolorallocatealpha($canvas, 255, 255, 255, 95);
            imagefill($img1_bordered, 0, 0, $borderColor);
            imagecopy($img1_bordered, $img1_resized, $borderSize, $borderSize, 0, 0, $imageSize, $imageSize);
            $resources[] = $img1_resized;
            $resources[] = $img1_bordered;

            $img2_resized = imagecreatetruecolor($imageSize, $imageSize);
            imagecopyresampled($img2_resized, $img2_src, 0, 0, 0, 0, $imageSize, $imageSize, imagesx($img2_src), imagesy($img2_src));
            $img2_bordered = imagecreatetruecolor($borderedImageSize, $borderedImageSize);
            imagefill($img2_bordered, 0, 0, $borderColor);
            imagecopy($img2_bordered, $img2_resized, $borderSize, $borderSize, 0, 0, $imageSize, $imageSize);
            $resources[] = $img2_resized;
            $resources[] = $img2_bordered;

            $gap = 60;
            $x1 = (1200 - ($borderedImageSize * 2 + $gap)) / 2;
            $y = (630 - $borderedImageSize) / 2;
            $x2 = $x1 + $borderedImageSize + $gap;
            imagecopy($canvas, $img1_bordered, $x1, $y, 0, 0, $borderedImageSize, $borderedImageSize);
            imagecopy($canvas, $img2_bordered, $x2, $y, 0, 0, $borderedImageSize, $borderedImageSize);

            // --- "VS" Separator ---
            $centerX = 1200 / 2;
            $centerY = 630 / 2;
            $vsCircleRadius = 100;
            $vsCircleColor = imagecolorallocatealpha($canvas, 0, 0, 0, 85);
            $vsTextColor = imagecolorallocatealpha($canvas, 255, 255, 255, 10);

            imagefilledellipse($canvas, $centerX, $centerY, $vsCircleRadius, $vsCircleRadius, $vsCircleColor);

            $vsText = 'VS';
            $vsFontSize = 40;
            $vsTextBox = imagettfbbox($vsFontSize, 0, $fontPath, $vsText);
            $vsTextWidth = $vsTextBox[2] - $vsTextBox[0];
            $vsTextHeight = $vsTextBox[1] - $vsTextBox[7];
            $vsTextX = $centerX - ($vsTextWidth / 2);
            $vsTextY = $centerY + ($vsTextHeight / 2);
            imagettftext($canvas, $vsFontSize, 0, $vsTextX, $vsTextY, $vsTextColor, $fontPath, $vsText);

            // ---  Watermark ---
            $badgeHeight = 45;
            $badgeWidth = 160;
            $badgePadding = 25;
            $badgeX = 1200 - $badgeWidth - $badgePadding;
            $badgeY = 630 - $badgeHeight - $badgePadding;
            $radius = $badgeHeight / 2;

            $startColor = imagecolorallocatealpha($canvas, 30, 30, 30, 40);
            $endColor = imagecolorallocatealpha($canvas, 0, 0, 0, 40);

            imagefilledrectangle($canvas, $badgeX + $radius, $badgeY, $badgeX + $badgeWidth - $radius, $badgeY + $badgeHeight, $startColor);
            imagefilledellipse($canvas, $badgeX + $radius, $badgeY + $radius, $badgeHeight, $badgeHeight, $startColor);
            imagefilledellipse($canvas, $badgeX + $badgeWidth - $radius, $badgeY + $radius, $badgeHeight, $badgeHeight, $endColor);

            // Watermark Text
            $watermarkText = 'GOAT.UZ';
            $watermarkFontSize = 20;
            $watermarkTextColor = imagecolorallocatealpha($canvas, 255, 255, 255, 15);
            $watermarkTextBox = imagettfbbox($watermarkFontSize, 0, $logoFontPath, $watermarkText);
            $watermarkTextWidth = $watermarkTextBox[2] - $watermarkTextBox[0];
            $watermarkTextHeight = $watermarkTextBox[1] - $watermarkTextBox[7];
            $watermarkTextX = $badgeX + ($badgeWidth - $watermarkTextWidth) / 2;
            $watermarkTextY = $badgeY + ($badgeHeight + $watermarkTextHeight) / 2;
            imagettftext($canvas, $watermarkFontSize, 0, $watermarkTextX, $watermarkTextY, $watermarkTextColor, $logoFontPath, $watermarkText);


            ob_start();
            imagejpeg($canvas, null, 90);
            $imageData = ob_get_clean();

            Log::info('Creative Telegram image generated successfully using GD', ['post_id' => $post->id]);
            return $imageData;

        } catch (Throwable $e) {
            Log::error('Error generating GD Telegram image: ' . $e->getMessage(), [
                'post_id' => $post->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        } finally {
            foreach ($resources as $resource) {
                if (is_resource($resource) || $resource instanceof GdImage) {
                    imagedestroy($resource);
                }
            }
        }
    }
}
