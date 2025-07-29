<?php

namespace App\Services;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\Models\Post;
use Exception;
use GdImage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class XService
{
    protected TwitterOAuth $client;

    public function __construct()
    {
        $apiKey = Config::get('services.x.api_key');
        $apiSecretKey = Config::get('services.x.api_secret_key');
        $accessToken = Config::get('services.x.access_token');
        $accessTokenSecret = Config::get('services.x.access_token_secret');

        if (!$apiKey || !$apiSecretKey || !$accessToken || !$accessTokenSecret) {
            throw new Exception('X API credentials are not fully configured.');
        }

        $this->client = new TwitterOAuth($apiKey, $apiSecretKey, $accessToken, $accessTokenSecret);
        $this->client->setApiVersion('2');
    }

    public function share(Post $post): void
    {
        $post = $post->fresh()->loadMissing('user');

        $imageData = $this->generateCompositeImage($post);
        if (!$imageData) {
            throw new Exception('Failed to generate the composite image for X.');
        }

        $tempImagePath = null;
        try {
            $tempFileName = 'temp/x_post_' . $post->id . '.jpg';
            Storage::disk('local')->put($tempFileName, $imageData);
            $tempImagePath = Storage::disk('local')->path($tempFileName);

            $this->client->setApiVersion('1.1');
            $media = $this->client->upload('media/upload', ['media' => $tempImagePath]);

            if ($this->client->getLastHttpCode() !== 200) {
                $errorBody = json_encode($this->client->getLastBody());
                throw new Exception('X media upload failed with HTTP status ' . $this->client->getLastHttpCode() . '. Response: ' . $errorBody);
            }
            if (!isset($media->media_id_string)) {
                throw new Exception('X media upload succeeded, but no media_id_string was returned.');
            }
            $mediaId = $media->media_id_string;

            $postUrl = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);

            $tweetText = "⚡️ " . $post->question . "\n\n";
            $tweetText .= "1️⃣ " . $post->option_one_title . "\n";
            $tweetText .= "2️⃣ " . $post->option_two_title . "\n\n";
            $tweetText .= "👉 Vote here: " . $postUrl;

            $payload = [
                'text' => $tweetText,
                'media' => [
                    'media_ids' => [$mediaId]
                ]
            ];

            $this->client->setApiVersion('2');
            $result = $this->client->post('tweets', $payload);

            if ($this->client->getLastHttpCode() < 200 || $this->client->getLastHttpCode() >= 300) {
                $errorResponse = json_encode($this->client->getLastBody());
                Log::error('Failed to post tweet to X', [
                    'post_id' => $post->id,
                    'http_code' => $this->client->getLastHttpCode(),
                    'response' => $errorResponse
                ]);
                throw new Exception('Failed to post tweet to X: ' . $errorResponse);
            }

            Log::info('Successfully shared post to X with an image', ['post_id' => $post->id]);

        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
        } finally {
            if ($tempImagePath && file_exists($tempImagePath)) {
                Storage::disk('local')->delete($tempFileName);
            }
        }
    }

    private function generateCompositeImage(Post $post): ?string
    {
        $resources = [];
        try {
            $imageOnePath = Storage::disk('public')->path($post->option_one_image);
            $imageTwoPath = Storage::disk('public')->path($post->option_two_image);
            $fontPath = storage_path('app/fonts/Inter-Bold.ttf');
            $logoFontPath = storage_path('app/fonts/Inter-Black.ttf');

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

            for ($i = 0; $i < 25; $i++) {
                imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
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
            return ob_get_clean();

        } catch (Throwable $e) {
            Log::error('Error generating GD image for X: ' . $e->getMessage(), [
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
