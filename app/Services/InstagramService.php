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

    private const CANVAS_WIDTH = 1080;
    private const CANVAS_HEIGHT = 1080;
    private const FONT_BOLD_PATH_KEY = 'app/fonts/Inter-Bold.ttf';
    private const FONT_BLACK_PATH_KEY = 'app/fonts/Inter-Black.ttf';

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

        $imageData = $this->generatePostImage($post);
        if (!$imageData) {
            throw new Exception('Failed to generate the composite image for Instagram.');
        }

        $tempFileName = 'temp/instagram_post_' . $post->id . '_' . time() . '.jpg';
        Storage::disk('public')->put($tempFileName, $imageData);
        $publicImageUrl = Storage::disk('public')->url($tempFileName);

        try {
            $caption = $this->createCaption($post);
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

    private function createCaption(Post $post): string
    {
        $caption = "⚡️ " . $post->question . "\n\n";
        $caption .= "1️⃣ " . $post->option_one_title . "\n";
        $caption .= "2️⃣ " . $post->option_two_title . "\n\n";

        if ($post->ai_generated_tags) {
            $tags = '#' . implode(' #', preg_split('/[,\s]+/', $post->ai_generated_tags));
            $caption .= $tags . "\n\n";
        }

        $caption .= "👉 Vote on our website! Link in bio. #goatuz";

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
        $responseData = $response->json();

        if (!$response->successful() || !isset($responseData['id'])) {
            throw new Exception('Failed to create Instagram media container. Response: ' . $response->body());
        }

        $this->waitForContainerReady($responseData['id']);

        return $responseData['id'];
    }

    private function waitForContainerReady(string $containerId): void
    {
        $url = "{$this->graphApiUrl}/{$containerId}";
        $params = ['fields' => 'status_code', 'access_token' => $this->accessToken];
        $maxAttempts = 12;
        $waitSeconds = 5;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($waitSeconds);
            $response = Http::get($url, $params);
            $responseData = $response->json();

            if (!$response->successful()) {
                throw new Exception('Failed to check Instagram container status. Response: ' . $response->body());
            }

            $status = $responseData['status_code'] ?? 'UNKNOWN';
            if ($status === 'FINISHED') {
                return;
            }
            if (in_array($status, ['ERROR', 'EXPIRED', 'FAILED'])) {
                throw new Exception("Instagram container processing failed with status: {$status}");
            }
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

    private function generatePostImage(Post $post): ?string
    {
        $resources = [];
        try {
            $imageOnePath = Storage::disk('public')->path($post->option_one_image);
            $imageTwoPath = Storage::disk('public')->path($post->option_two_image);
            $fontBoldPath = storage_path(self::FONT_BOLD_PATH_KEY);
            $fontBlackPath = storage_path(self::FONT_BLACK_PATH_KEY);

            if (!File::exists($imageOnePath) || !File::exists($imageTwoPath) || !File::exists($fontBoldPath) || !File::exists($fontBlackPath)) {
                Log::error('Source image or font file not found for Instagram post generation.', ['post_id' => $post->id]);
                return null;
            }

            $img1_src = imagecreatefromwebp($imageOnePath);
            $img2_src = imagecreatefromwebp($imageTwoPath);
            if (!$img1_src || !$img2_src) return null;
            $resources[] = $img1_src;
            $resources[] = $img2_src;
            $canvas = imagecreatetruecolor(self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
            $resources[] = $canvas;
            $this->addBackgroundImage($canvas, $img1_src, $img2_src);
            $this->addForegroundImages($canvas, $img1_src, $img2_src);
            $this->addVsElement($canvas, $fontBoldPath);
            $this->addWatermarkBadge($canvas, $fontBlackPath);
            ob_start();
            imagejpeg($canvas, null, 95);
            return ob_get_clean();

        } catch (Throwable $e) {
            Log::error('Error generating GD image for Instagram.', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
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

    private function addBackgroundImage(GdImage $canvas, GdImage $img1, GdImage $img2): void
    {
        $halfWidth = self::CANVAS_WIDTH / 2;

        $leftHalf = $this->createCroppedImage($img1, $halfWidth, self::CANVAS_HEIGHT);
        $rightHalf = $this->createCroppedImage($img2, $halfWidth, self::CANVAS_HEIGHT);

        imagecopy($canvas, $leftHalf, 0, 0, 0, 0, $halfWidth, self::CANVAS_HEIGHT);
        imagecopy($canvas, $rightHalf, $halfWidth, 0, 0, 0, $halfWidth, self::CANVAS_HEIGHT);

        imagedestroy($leftHalf);
        imagedestroy($rightHalf);

        for ($i = 0; $i < 25; $i++) {
            imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
        }
        $overlay = imagecolorallocatealpha($canvas, 0, 0, 0, 80);
        imagefilledrectangle($canvas, 0, 0, self::CANVAS_WIDTH, self::CANVAS_HEIGHT, $overlay);
    }

    private function addForegroundImages(GdImage $canvas, GdImage $img1, GdImage $img2): void
    {
        $imageSize = 450;
        $borderSize = 10;
        $borderedImageSize = $imageSize + ($borderSize * 2);
        $borderColor = imagecolorallocatealpha($canvas, 255, 255, 255, 90);

        $createBorderedImage = function (GdImage $source) use ($imageSize, $borderSize, $borderedImageSize, $borderColor) {
            $cropped = $this->createCroppedImage($source, $imageSize, $imageSize);
            $bordered = imagecreatetruecolor($borderedImageSize, $borderedImageSize);
            imagefill($bordered, 0, 0, $borderColor);
            imagecopy($bordered, $cropped, $borderSize, $borderSize, 0, 0, $imageSize, $imageSize);
            imagedestroy($cropped);
            return $bordered;
        };

        $img1_bordered = $createBorderedImage($img1);
        $img2_bordered = $createBorderedImage($img2);

        $gap = 60;
        $x1 = (self::CANVAS_WIDTH - ($borderedImageSize * 2 + $gap)) / 2;
        $y = (self::CANVAS_HEIGHT - $borderedImageSize) / 2;
        $x2 = $x1 + $borderedImageSize + $gap;

        imagecopy($canvas, $img1_bordered, $x1, $y, 0, 0, $borderedImageSize, $borderedImageSize);
        imagecopy($canvas, $img2_bordered, $x2, $y, 0, 0, $borderedImageSize, $borderedImageSize);

        imagedestroy($img1_bordered);
        imagedestroy($img2_bordered);
    }

    private function addVsElement(GdImage $canvas, string $fontPath): void
    {
        $centerX = self::CANVAS_WIDTH / 2;
        $centerY = self::CANVAS_HEIGHT / 2;

        $vsCircleRadius = 120;
        $vsCircleColor = imagecolorallocatealpha($canvas, 0, 0, 0, 90);
        imagefilledellipse($canvas, $centerX, $centerY, $vsCircleRadius, $vsCircleRadius, $vsCircleColor);

        $vsText = 'VS';
        $vsFontSize = 48;
        $vsTextColor = imagecolorallocatealpha($canvas, 255, 255, 255, 15);
        $textBox = imagettfbbox($vsFontSize, 0, $fontPath, $vsText);
        $textX = $centerX - (($textBox[2] - $textBox[0]) / 2);
        $textY = $centerY + (($textBox[1] - $textBox[7]) / 2);
        imagettftext($canvas, $vsFontSize, 0, $textX, $textY, $vsTextColor, $fontPath, $vsText);
    }

    private function addWatermarkBadge(GdImage $canvas, string $fontPath): void
    {
        $badgeHeight = 45;
        $badgeWidth = 160;
        $padding = 30;

        $badgeX = self::CANVAS_WIDTH - $badgeWidth - $padding;
        $badgeY = self::CANVAS_HEIGHT - $badgeHeight - $padding;
        $radius = $badgeHeight / 2;

        $bgColor = imagecolorallocatealpha($canvas, 15, 15, 15, 50);
        imagefilledrectangle($canvas, $badgeX + $radius, $badgeY, $badgeX + $badgeWidth - $radius, $badgeY + $badgeHeight, $bgColor);
        imagefilledellipse($canvas, $badgeX + $radius, $badgeY + $radius, $badgeHeight, $badgeHeight, $bgColor);
        imagefilledellipse($canvas, $badgeX + $badgeWidth - $radius, $badgeY + $radius, $badgeHeight, $badgeHeight, $bgColor);

        $watermarkText = 'GOAT.UZ';
        $watermarkFontSize = 20;
        $textColor = imagecolorallocatealpha($canvas, 255, 255, 255, 50);
        $textBox = imagettfbbox($watermarkFontSize, 0, $fontPath, $watermarkText);
        $textX = $badgeX + ($badgeWidth - ($textBox[2] - $textBox[0])) / 2;
        $textY = $badgeY + ($badgeHeight + ($textBox[1] - $textBox[7])) / 2;
        imagettftext($canvas, $watermarkFontSize, 0, $textX, $textY, $textColor, $fontPath, $watermarkText);
    }

    private function createCroppedImage(GdImage $sourceImage, int $targetWidth, int $targetHeight): GdImage
    {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        $srcX = 0;
        $srcY = 0;
        $srcW = $sourceWidth;
        $srcH = $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $srcH = $sourceHeight;
            $srcW = (int)($sourceHeight * $targetRatio);
            $srcX = (int)(($sourceWidth - $srcW) / 2);
        } else {
            $srcW = $sourceWidth;
            $srcH = (int)($sourceWidth / $targetRatio);
            $srcY = (int)(($sourceHeight - $srcH) / 2);
        }

        $croppedImage = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $croppedImage, $sourceImage,
            0, 0, $srcX, $srcY,
            $targetWidth, $targetHeight,
            $srcW, $srcH
        );

        return $croppedImage;
    }
}
