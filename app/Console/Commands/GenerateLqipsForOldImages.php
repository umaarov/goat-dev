<?php

namespace App\Console\Commands;

use App\Models\Post;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateLqipsForOldImages extends Command
{
    protected $signature = 'images:generate-lqips';

    protected $description = 'Generate Low-Quality Image Placeholders for old posts that are missing them.';

    private const LQIP_WIDTH = 24;
    private const LQIP_QUALITY = 30;

    public function handle()
    {
        $this->info('Starting LQIP generation for old images...');

        $query = Post::query()->where(function ($q) {
            $q->whereNotNull('option_one_image')->whereNull('option_one_image_lqip');
        })->orWhere(function ($q) {
            $q->whereNotNull('option_two_image')->whereNull('option_two_image_lqip');
        });

        $count = $query->count();
        if ($count === 0) {
            $this->info('No images need processing. All posts have LQIPs.');
            return 0;
        }

        $this->info("Found {$count} posts to process.");
        $progressBar = $this->output->createProgressBar($count);

        $query->chunkById(100, function ($posts) use ($progressBar) {
            foreach ($posts as $post) {
                $updates = [];

                if ($post->option_one_image && !$post->option_one_image_lqip) {
                    $lqip = $this->generateLqipForPath($post->option_one_image);
                    if ($lqip) {
                        $updates['option_one_image_lqip'] = $lqip;
                    }
                }

                if ($post->option_two_image && !$post->option_two_image_lqip) {
                    $lqip = $this->generateLqipForPath($post->option_two_image);
                    if ($lqip) {
                        $updates['option_two_image_lqip'] = $lqip;
                    }
                }

                if (!empty($updates)) {
                    DB::table('posts')->where('id', $post->id)->update($updates);
                }
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->info("\n✅ Done. LQIP generation complete.");
        return 0;
    }

    private function generateLqipForPath(string $imagePath): ?string
    {
        if (!Storage::disk('public')->exists($imagePath)) {
            return null;
        }

        $fullImagePath = Storage::disk('public')->path($imagePath);
        $binaryPath = base_path('image_processor');

        if (!is_executable($binaryPath)) {
            $this->error("Error: C binary not found or not executable at {$binaryPath}");
            Log::error("C binary not found at {$binaryPath}");
            return null;
        }

        try {
            $command = sprintf(
                '%s %s %s %d %d %d %d %d',
                escapeshellcmd($binaryPath),
                escapeshellarg($fullImagePath), // <input>
                'dummy_output.webp',            // <output_webp> (not used for LQIP)
                1, 1, 1,                        // dummy width, height, quality
                self::LQIP_WIDTH,               // <lqip_width>
                self::LQIP_QUALITY              // <lqip_quality>
            );

            $lqipBase64 = exec($command, $output, $returnCode);

            if ($returnCode !== 0 || empty($lqipBase64)) {
                throw new Exception('C binary failed to generate LQIP. Code: ' . $returnCode);
            }

            return 'data:image/jpeg;base64,' . $lqipBase64;
        } catch (Exception $e) {
            Log::error('LQIP generation failed for path: ' . $imagePath, ['error' => $e->getMessage()]);
            return null;
        }
    }
}
