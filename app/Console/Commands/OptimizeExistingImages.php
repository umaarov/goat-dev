<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class OptimizeExistingImages extends Command
{
    private const MAX_POST_IMAGE_WIDTH = 500;
    private const MAX_POST_IMAGE_HEIGHT = 500;
    private const PROFILE_IMAGE_SIZE = 150;
    private const IMAGE_QUALITY = 80;
    protected $signature = 'images:optimize {--force : Force re-processing of WebP images}';
    protected $description = 'Optimizes existing post and profile images to 500px WebP format.';

    public function handle(): void
    {
        $this->info('Starting image optimization process (Target: 500px)...');
        $manager = new ImageManager(new Driver());
        $force = $this->option('force');

        $this->info('Optimizing Post images...');
        Post::chunkById(100, function ($posts) use ($manager, $force) {
            foreach ($posts as $post) {
                $this->processPostImageField($post, 'option_one_image', $manager, $force);
                $this->processPostImageField($post, 'option_two_image', $manager, $force);
            }
            $this->output->write('.');
        });
        $this->info("\nPost image optimization complete.");

        $this->info('Optimizing User profile images...');
        User::chunkById(100, function ($users) use ($manager, $force) {
            foreach ($users as $user) {
                $this->processProfileImageField($user, 'profile_picture', $manager, $force);
            }
            $this->output->write('.');
        });
        $this->info("\nUser profile image optimization complete.");
    }

    private function processPostImageField(Post $post, string $field, ImageManager $manager, bool $force): void
    {
        $originalPath = $post->$field;

        if (!$originalPath) return;
        if (!$force && str_ends_with($originalPath, '.webp')) {
            return;
        }

        if (Storage::disk('public')->exists($originalPath)) {
            try {
                $imageFile = Storage::disk('public')->get($originalPath);
                $image = $manager->read($imageFile);

                $image->cover(self::MAX_POST_IMAGE_WIDTH, self::MAX_POST_IMAGE_HEIGHT);

                $newExtension = 'webp';
                $newFilename = pathinfo($originalPath, PATHINFO_FILENAME);

                if (pathinfo($originalPath, PATHINFO_EXTENSION) !== 'webp') {
                    $newFilename .= '.' . $newExtension;
                } else {
                    $newFilename = basename($originalPath);
                }

                $newPath = 'post_images/' . $newFilename;

                $encodedImage = $image->encode(new WebpEncoder(quality: self::IMAGE_QUALITY));

                Storage::disk('public')->put($newPath, $encodedImage);

                if ($post->$field !== $newPath) {
                    $post->$field = $newPath;
                    $post->save();
                }

                if ($originalPath !== $newPath) {
                    Storage::disk('public')->delete($originalPath);
                }

            } catch (Exception $e) {
                $this->error("Failed post image: {$originalPath} (ID {$post->id}) - " . $e->getMessage());
                Log::error("Image optimization failed", ['id' => $post->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function processProfileImageField(User $user, string $field, ImageManager $manager, bool $force): void
    {
        $originalPath = $user->$field;

        if (!$originalPath || filter_var($originalPath, FILTER_VALIDATE_URL)) {
            return;
        }

        if (!$force && str_ends_with($originalPath, '.webp')) {
            return;
        }

        if (Storage::disk('public')->exists($originalPath)) {
            try {
                $imageFile = Storage::disk('public')->get($originalPath);
                $image = $manager->read($imageFile);

                $image->cover(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);

                $newFilename = pathinfo($originalPath, PATHINFO_FILENAME);
                if (pathinfo($originalPath, PATHINFO_EXTENSION) !== 'webp') {
                    $newFilename .= '.webp';
                } else {
                    $newFilename = basename($originalPath);
                }

                $newPath = 'profile_pictures/' . $newFilename;

                $encodedImage = $image->encode(new WebpEncoder(quality: self::IMAGE_QUALITY));

                Storage::disk('public')->put($newPath, $encodedImage);

                if ($user->$field !== $newPath) {
                    $user->$field = $newPath;
                    $user->save();
                }

                if ($originalPath !== $newPath) {
                    Storage::disk('public')->delete($originalPath);
                }

            } catch (Exception $e) {
                $this->error("Failed profile image: {$originalPath} (User {$user->id})");
            }
        }
    }
}
