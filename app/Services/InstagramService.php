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

class InstagramService
{
    private ?string $accountId;
    private ?string $accessToken;
    private string $graphApiUrl;

    public function __construct()
    {
        $this->accountId = Config::get('services.instagram.business_account_id');
        $this->accessToken = Config::get('services.instagram.access_token');
        $this->graphApiUrl = rtrim(Config::get('services.instagram.graph_url'), '/')
            . '/' . Config::get('services.instagram.graph_api_version');

        if (!$this->accountId || !$this->accessToken) {
            throw new Exception('Instagram Business Account ID and Access Token must be configured.');
        }
    }

    public function share(Post $post): void
    {
        $post = $post->fresh()->loadMissing('user');

        $imageData = $this->generateInstagramImage($post);
        if (!$imageData) {
            throw new Exception('Failed to generate the composite image for Instagram.');
        }

        $tempFileName = 'temp/instagram_post_' . $post->id . '_' . time() . '.jpg';
        Storage::disk('public')->put($tempFileName, $imageData);
        $publicImageUrl = Storage::disk('public')->url($tempFileName);

        try {
            $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);
            $caption = $this->createCaption($post, $postUrl);
            $containerId = $this->createPhotoContainer($publicImageUrl, $caption);

            $this->publishContainer($containerId);

            Log::info('Successfully published post to Instagram.', ['post_id' => $post->id]);

        } catch (Throwable $e) {
            throw new Exception("Instagram sharing failed: " . $e->getMessage());
        } finally {
            if (Storage::disk('public')->exists($tempFileName)) {
                Storage::disk('public')->delete($tempFileName);
            }
        }
    }

    private function createCaption(Post $post, string $postUrl): string
    {
        $caption = $post->question . "\n\n";
        $caption .= "1️⃣ " . $post->option_one_title . "\n";
        $caption .= "2️⃣ " . $post->option_two_title . "\n\n";

        if ($post->ai_generated_tags) {
            $tags = '#' . str_replace([',', ' '], ['', ' #'], $post->ai_generated_tags);
            $caption .= $tags . "\n\n";
        }

        $caption .= "Vote now on GOAT.UZ! 🐐 Link in bio!";
        return $caption;
    }

    private function createPhotoContainer(string $imageUrl, string $caption): string
    {
        $url = "{$this->graphApiUrl}/{$this->accountId}/media";
        $params = [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->accessToken,
        ];

        $response = Http::timeout(60)->post($url, $params);

        if (!$response->successful()) {
            throw new Exception('Failed to create Instagram media container. Response: ' . $response->body());
        }

        $containerId = $response->json('id');
        if (!$containerId) {
            throw new Exception('Could not get container ID from Instagram API response. Response: ' . $response->body());
        }

        $this->waitForContainerReady($containerId);

        return $containerId;
    }

    private function waitForContainerReady(string $containerId): void
    {
        $url = "{$this->graphApiUrl}/{$containerId}";
        $params = ['fields' => 'status_code', 'access_token' => $this->accessToken];
        $maxAttempts = 12;
        $waitSeconds = 5;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::get($url, $params);

            if (!$response->successful()) {
                throw new Exception('Failed to check Instagram container status. Response: ' . $response->body());
            }

            $status = $response->json('status_code');
            if ($status === 'FINISHED') {
                return;
            }
            if ($status === 'ERROR' || $status === 'EXPIRED' || $status === 'FAILED') {
                throw new Exception("Instagram container processing failed with status: {$status}");
            }

            sleep($waitSeconds);
        }

        throw new Exception("Instagram media container did not become ready in time.");
    }

    private function publishContainer(string $containerId): void
    {
        $url = "{$this->graphApiUrl}/{$this->accountId}/media_publish";
        $params = ['creation_id' => $containerId, 'access_token' => $this->accessToken];
        $response = Http::post($url, $params);

        if (!$response->successful()) {
            throw new Exception('Failed to publish Instagram media container. Response: ' . $response->body());
        }
    }

    private function generateInstagramImage(Post $post): ?string
    {
        $resources = [];
        try {
            $imageOnePath = Storage::disk('public')->path($post->option_one_image);
            $imageTwoPath = Storage::disk('public')->path($post->option_two_image);
            $fontPath = storage_path('app/fonts/Inter-Bold.ttf');
            $logoFontPath = storage_path('app/fonts/Inter-Black.ttf');

            if (!File::exists($imageOnePath) || !File::exists($imageTwoPath)) {
                Log::error('Source images not found for Instagram post generation.', ['post_id' => $post->id]);
                return null;
            }

            $canvasWidth = 1080;
            $canvasHeight = 1080;
            $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
            $resources[] = $canvas;

            $img1_src = imagecreatefromwebp($imageOnePath);
            $img2_src = imagecreatefromwebp($imageTwoPath);
            $resources[] = $img1_src;
            $resources[] = $img2_src;
            imagecopyresampled($canvas, $img1_src, 0, 0, 0, 0, $canvasWidth / 2, $canvasHeight, imagesx($img1_src), imagesy($img1_src));
            imagecopyresampled($canvas, $img2_src, $canvasWidth / 2, 0, 0, 0, $canvasWidth / 2, $canvasHeight, imagesx($img2_src), imagesy($img2_src));
            for ($i = 0; $i < 30; $i++) {
                imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
            }
            $overlayColor = imagecolorallocatealpha($canvas, 0, 0, 0, 80);
            imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $overlayColor);

            $imageSize = 450;
            $borderSize = 10;
            $borderedImageSize = $imageSize + ($borderSize * 2);
            $borderColor = imagecolorallocatealpha($canvas, 255, 255, 255, 95);

            $img1_resized = imagecreatetruecolor($imageSize, $imageSize);
            imagecopyresampled($img1_resized, $img1_src, 0, 0, 0, 0, $imageSize, $imageSize, imagesx($img1_src), imagesy($img1_src));
            $img1_bordered = imagecreatetruecolor($borderedImageSize, $borderedImageSize);
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
            $x1 = ($canvasWidth - ($borderedImageSize * 2 + $gap)) / 2;
            $y = ($canvasHeight - $borderedImageSize) / 2;
            $x2 = $x1 + $borderedImageSize + $gap;
            imagecopy($canvas, $img1_bordered, $x1, $y, 0, 0, $borderedImageSize, $borderedImageSize);
            imagecopy($canvas, $img2_bordered, $x2, $y, 0, 0, $borderedImageSize, $borderedImageSize);

            $centerX = $canvasWidth / 2;
            $centerY = $canvasHeight / 2;
            $vsCircleRadius = 120;
            $vsCircleColor = imagecolorallocatealpha($canvas, 0, 0, 0, 90);
            $vsTextColor = imagecolorallocatealpha($canvas, 255, 255, 255, 10);
            imagefilledellipse($canvas, $centerX, $centerY, $vsCircleRadius, $vsCircleRadius, $vsCircleColor);
            $vsText = 'VS';
            $vsFontSize = 48;
            $vsTextBox = imagettfbbox($vsFontSize, 0, $fontPath, $vsText);
            $vsTextX = $centerX - (($vsTextBox[2] - $vsTextBox[0]) / 2);
            $vsTextY = $centerY + (($vsTextBox[1] - $vsTextBox[7]) / 2);
            imagettftext($canvas, $vsFontSize, 0, $vsTextX, $vsTextY, $vsTextColor, $fontPath, $vsText);

            $watermarkText = 'GOAT.UZ';
            $watermarkFontSize = 24;
            $watermarkTextColor = imagecolorallocatealpha($canvas, 255, 255, 255, 60);
            $watermarkTextBox = imagettfbbox($watermarkFontSize, 0, $logoFontPath, $watermarkText);
            $watermarkTextX = ($canvasWidth - ($watermarkTextBox[2] - $watermarkTextBox[0])) / 2;
            $watermarkTextY = $canvasHeight - 40;
            imagettftext($canvas, $watermarkFontSize, 0, $watermarkTextX, $watermarkTextY, $watermarkTextColor, $logoFontPath, $watermarkText);

            ob_start();
            imagejpeg($canvas, null, 90);
            return ob_get_clean();

        } catch (Throwable $e) {
            Log::error('Error generating GD image for Instagram: ' . $e->getMessage(), ['post_id' => $post->id, 'trace' => $e->getTraceAsString()]);
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
