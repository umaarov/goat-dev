<?php

namespace App\Services;

use App\Models\Post;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class TelegramService
{
    public function share(Post $post): void
    {
        $post = $post->fresh();

        if (!$post) {
            throw new Exception('Post not found when trying to share to Telegram.');
        }

        $botToken = Config::get('services.telegram.bot_token');
        $chatId = Config::get('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            throw new Exception('Telegram bot token or chat ID is not configured.');
        }

        Log::info('Attempting to share post to Telegram', [
            'post_id' => $post->id,
            'post_attributes' => $post->getAttributes(),
            'has_option_one_image' => !empty($post->option_one_image),
            'has_option_two_image' => !empty($post->option_two_image)
        ]);

        $imageData = $this->generateTelegramImage($post);
        if (!$imageData) {
            throw new Exception('Failed to generate the composite image.');
        }

        $postUrl = route('posts.show', ['post' => $post->id]);
        $caption = "⚡️ " . $post->question . "\n\n";
        $caption .= "1️⃣ " . $post->option_one_title . "\n";
        $caption .= "2️⃣ " . $post->option_two_title . "\n\n";
        $caption .= "👉 Vote and see the results:\n" . $postUrl;

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendPhoto";

        Log::info('Sending image to Telegram', [
            'post_id' => $post->id,
            'image_size' => strlen($imageData),
            'caption_length' => strlen($caption),
            'api_url' => str_replace($botToken, '[REDACTED]', $apiUrl)
        ]);

        $response = Http::timeout(30)->attach(
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
                'image_size' => strlen($imageData),
                'caption_length' => strlen($caption)
            ]);
            throw new Exception('Telegram API request failed: ' . $response->body());
        }

        Log::info('Successfully shared post to Telegram', [
            'post_id' => $post->id,
            'response_status' => $response->status()
        ]);
    }

    private function generateTelegramImage(Post $post): ?string
    {
        try {
            // Add detailed logging for debugging
            Log::info('Generating Telegram image', [
                'post_id' => $post->id,
                'option_one_image' => $post->option_one_image,
                'option_two_image' => $post->option_two_image,
                'post_data' => [
                    'user_id' => $post->user_id,
                    'question' => $post->question,
                    'option_one_title' => $post->option_one_title,
                    'option_two_title' => $post->option_two_title
                ]
            ]);

            if (!$post->option_one_image || !$post->option_two_image) {
                Log::error('Post missing image paths', [
                    'post_id' => $post->id,
                    'option_one_image' => $post->option_one_image,
                    'option_two_image' => $post->option_two_image,
                    'all_attributes' => $post->getAttributes()
                ]);
                return null;
            }

            $manager = new ImageManager(new GdDriver());

            // Check if images exist in storage
            if (!Storage::disk('public')->exists($post->option_one_image)) {
                Log::error('Option one image does not exist in storage', [
                    'post_id' => $post->id,
                    'path' => $post->option_one_image,
                    'full_path' => Storage::disk('public')->path($post->option_one_image)
                ]);
                return null;
            }

            if (!Storage::disk('public')->exists($post->option_two_image)) {
                Log::error('Option two image does not exist in storage', [
                    'post_id' => $post->id,
                    'path' => $post->option_two_image,
                    'full_path' => Storage::disk('public')->path($post->option_two_image)
                ]);
                return null;
            }

            $imageOnePath = Storage::disk('public')->path($post->option_one_image);
            $imageTwoPath = Storage::disk('public')->path($post->option_two_image);

            Log::info('Image paths resolved', [
                'post_id' => $post->id,
                'path1' => $imageOnePath,
                'path2' => $imageTwoPath,
                'exists1' => File::exists($imageOnePath),
                'exists2' => File::exists($imageTwoPath)
            ]);

            if (!File::exists($imageOnePath) || !File::exists($imageTwoPath)) {
                Log::error('Source image files not found on filesystem', [
                    'post_id' => $post->id,
                    'path1' => $imageOnePath,
                    'path2' => $imageTwoPath,
                    'exists1' => File::exists($imageOnePath),
                    'exists2' => File::exists($imageTwoPath)
                ]);
                return null;
            }

            $img1 = $manager->read($imageOnePath);
            $img2 = $manager->read($imageTwoPath);
            $canvas = $manager->create(1200, 630, '#1a1a1a');

            $img1->cover(550, 550);
            $img2->cover(550, 550);

            $x1 = (1200 - (550 * 2 + 40)) / 2;
            $y1 = (630 - 550) / 2;
            $x2 = $x1 + 550 + 40;

            $canvas->place($img1, 'top-left', $x1, $y1);
            $canvas->place($img2, 'top-left', $x2, $y1);

            $fontPath = storage_path('app/fonts/Inter-Bold.ttf');
            if (!File::exists($fontPath)) $fontPath = 1;

            $canvas->text('VS', 600, 315, function ($font) use ($fontPath) {
                $font->file($fontPath)->size(96)->color('#ffffff')->align('center')->valign('middle');
            });

            $canvas->text('goat.uz', 1170, 600, function ($font) use ($fontPath) {
                if (is_string($fontPath)) $font->file($fontPath);
                $font->size(32)->color('rgba(255, 255, 255, 0.5)')->align('right')->valign('bottom');
            });

            $imageData = (string)$canvas->encode(new JpegEncoder(quality: 85));

            Log::info('Telegram image generated successfully', [
                'post_id' => $post->id,
                'image_size' => strlen($imageData)
            ]);

            return $imageData;

        } catch (Exception $e) {
            Log::error('Error generating Telegram image: ' . $e->getMessage(), [
                'post_id' => $post->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
