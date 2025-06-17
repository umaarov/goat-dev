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

// --- IMPORTANT: ADD THIS NEW IMPORT ---

class OptimizeExistingImages extends Command
{
    protected $signature = 'images:optimize';
    protected $description = 'Optimizes existing post and profile images to WebP format.';

    private const MAX_POST_IMAGE_WIDTH = 1024;
    private const PROFILE_IMAGE_SIZE = 300;
    private const IMAGE_QUALITY = 75;

    public function handle(): void
    {
        $this->info('Starting image optimization process...');
        $manager = new ImageManager(new Driver());

        $this->info('Optimizing Post images...');
        Post::chunkById(100, function ($posts) use ($manager) {
            foreach ($posts as $post) {
                $this->processPostImageField($post, 'option_one_image', $manager);
                $this->processPostImageField($post, 'option_two_image', $manager);
            }
            $this->output->write('.');
        });
        $this->info("\nPost image optimization complete.");

        $this->info('Optimizing User profile images...');
        User::chunkById(100, function ($users) use ($manager) {
            foreach ($users as $user) {
                $this->processProfileImageField($user, 'profile_picture', $manager);
            }
            $this->output->write('.');
        });
        $this->info("\nUser profile image optimization complete.");

        $this->info('All images have been optimized successfully!');
    }

    private function processPostImageField(Post $post, string $field, ImageManager $manager): void
    {
        $originalPath = $post->$field;

        if (!$originalPath || str_ends_with($originalPath, '.webp')) {
            return;
        }

        if (Storage::disk('public')->exists($originalPath)) {
            try {
                $imageFile = Storage::disk('public')->get($originalPath);
                $image = $manager->read($imageFile);

                $image->scaleDown(width: self::MAX_POST_IMAGE_WIDTH);

                $newExtension = 'webp';
                $newFilename = pathinfo($originalPath, PATHINFO_FILENAME) . '.' . $newExtension;
                $newPath = 'post_images/' . $newFilename;

                $encodedImage = $image->encode(new WebpEncoder(quality: self::IMAGE_QUALITY));

                Storage::disk('public')->put($newPath, $encodedImage);

                $post->$field = $newPath;
                $post->save();

                Storage::disk('public')->delete($originalPath);

            } catch (Exception $e) {
                $this->error("Failed to process post image {$originalPath} for Post ID {$post->id}. Error: " . $e->getMessage());
                Log::error("Image optimization failed for post", ['msg' => $e->getMessage(), 'post_id' => $post->id, 'field' => $field]);
            }
        }
    }

    private function processProfileImageField(User $user, string $field, ImageManager $manager): void
    {
        $originalPath = $user->$field;
        if (!$originalPath || str_ends_with($originalPath, '.webp') || filter_var($originalPath, FILTER_VALIDATE_URL)) {
            return;
        }

        if (Storage::disk('public')->exists($originalPath)) {
            try {
                $imageFile = Storage::disk('public')->get($originalPath);
                $image = $manager->read($imageFile);

                $image->coverDown(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE);

                $newExtension = 'webp';
                $newFilename = pathinfo($originalPath, PATHINFO_FILENAME) . '.' . $newExtension;
                $newPath = 'profile_pictures/' . $newFilename;

                $encodedImage = $image->encode(new WebpEncoder(quality: self::IMAGE_QUALITY));

                Storage::disk('public')->put($newPath, $encodedImage);
                $user->$field = $newPath;
                $user->save();
                Storage::disk('public')->delete($originalPath);

            } catch (Exception $e) {
                $this->error("Failed to process profile image {$originalPath} for User ID {$user->id}. Error: " . $e->getMessage());
                Log::error("Image optimization failed for user", ['msg' => $e->getMessage(), 'user_id' => $user->id, 'field' => $field]);
            }
        }
    }
}
