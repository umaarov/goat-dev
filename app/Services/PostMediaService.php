<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Centralises post-image processing (resize -> webp + LQIP placeholder).
 *
 * Extracted from PostController so both the web controller and the mobile
 * API share one implementation. Behaviour is identical to the original
 * PostController::processAndStoreImage(): it prefers the native
 * `image_processor` binary and falls back to the GD driver.
 */
class PostMediaService
{
    public const MAX_POST_IMAGE_WIDTH = 1024;

    public const MAX_POST_IMAGE_HEIGHT = 1024;

    public const POST_IMAGE_QUALITY = 75;

    public const LQIP_QUALITY = 30;

    public const LQIP_WIDTH = 24;

    public const MAX_POST_IMAGE_SIZE_KB = 2048;

    public const MAX_POST_IMAGE_SIZE_MB = self::MAX_POST_IMAGE_SIZE_KB / 1024;

    /**
     * Process and store a single uploaded post image.
     *
     * @return array{main:string,lqip:string}
     */
    public function processAndStore(UploadedFile $uploadedFile, string $directory, string $baseFilename): array
    {
        $mainImageFilename = $baseFilename.'.webp';
        $mainImagePath = $directory.'/'.$mainImageFilename;
        $tempPath = $uploadedFile->getRealPath();
        $finalStoragePath = Storage::disk('public')->path($mainImagePath);

        $binaryPath = base_path('image_processor');

        if (is_executable($binaryPath)) {
            try {
                $command = sprintf(
                    '%s %s %s %d %d %d %d %d',
                    escapeshellcmd($binaryPath),
                    escapeshellarg($tempPath),
                    escapeshellarg($finalStoragePath),
                    self::MAX_POST_IMAGE_WIDTH,
                    self::MAX_POST_IMAGE_WIDTH,
                    self::POST_IMAGE_QUALITY,
                    self::LQIP_WIDTH,
                    self::LQIP_QUALITY
                );
                $lqipBase64 = exec($command, $output, $returnCode);
                if ($returnCode === 0) {
                    return ['main' => $mainImagePath, 'lqip' => 'data:image/jpeg;base64,'.$lqipBase64];
                }
            } catch (Exception $e) {
                Log::error('PostMediaService: binary failed, using GD fallback. '.$e->getMessage());
            }
        }

        $manager = new ImageManager(new GdDriver);
        $image = $manager->read($tempPath);
        $image->scaleDown(width: self::MAX_POST_IMAGE_WIDTH);
        Storage::disk('public')->put($mainImagePath, $image->encode(new WebpEncoder(quality: self::POST_IMAGE_QUALITY)));

        $lqip = (string) $image->scale(width: self::LQIP_WIDTH)->blur(5)->encode(new JpegEncoder(quality: self::LQIP_QUALITY))->toDataUri();

        return ['main' => $mainImagePath, 'lqip' => $lqip];
    }

    /**
     * Delete a post's stored media files (main + lqip for both options).
     */
    public function deletePostMedia(string ...$paths): void
    {
        $files = array_values(array_filter($paths, fn ($p) => $p && ! str_starts_with($p, 'data:')));

        if (! empty($files)) {
            Storage::disk('public')->delete($files);
        }
    }
}
