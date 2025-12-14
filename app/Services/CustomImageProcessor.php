<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\Process;

class CustomImageProcessor
{
    public function process(string $inputPath, string $outputRelativePath, int $width, int $height, int $quality, int $lqipWidth, int $lqipQuality): array
    {

        $fullOutputPath = Storage::disk('public')->path($outputRelativePath);
        $binaryPath = base_path('image_processor');

        if (file_exists($binaryPath) && is_executable($binaryPath)) {
            try {
                $directory = dirname($fullOutputPath);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                $process = new Process([$binaryPath, $inputPath, $fullOutputPath, (string)$width, (string)$height, (string)$quality, (string)$lqipWidth, (string)$lqipQuality]);

                $process->setTimeout(10);
                $process->mustRun();

                $lqipBase64 = trim($process->getOutput());

                Log::info('Image processed with custom C binary.');

                return ['main' => $outputRelativePath, 'lqip' => 'data:image/jpeg;base64,' . $lqipBase64];

            } catch (Exception $e) {
                Log::warning('Custom C binary failed, falling back to GD.', ['error' => $e->getMessage()]);
            }
        }

        return $this->processWithPhp($inputPath, $outputRelativePath, $width, $quality, $lqipWidth, $lqipQuality);
    }

    private function processWithPhp($inputPath, $outputRelativePath, $width, $quality, $lqipWidth, $lqipQuality): array
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($inputPath);

        $image->scaleDown(width: $width);
        $encodedMainImage = $image->encode(new WebpEncoder(quality: $quality));
        Storage::disk('public')->put($outputRelativePath, $encodedMainImage);

        $lqip = (string)$image->scale(width: $lqipWidth)->blur(5)->encode(new JpegEncoder(quality: $lqipQuality))->toDataUri();

        return ['main' => $outputRelativePath, 'lqip' => $lqip];
    }
}
