<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\InstagramService;
use App\Services\TelegramService;
use App\Services\XService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SharePostToSocialMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 180;
    public $backoff = [30, 60, 120];

    public Post $post;

    public function __construct(Post $post)
    {
        $this->post = $post;
    }

    public function handle(
        TelegramService  $telegramService,
        InstagramService $instagramService,
        XService         $xService
    ): void
    {
        $freshPost = Post::find($this->post->id);

        if (!$freshPost) {
            Log::error('Post not found in social media sharing job');
            return;
        }

        $keys = [
            'telegram' => "post_{$freshPost->id}_shared_telegram",
            'instagram' => "post_{$freshPost->id}_shared_instagram",
            'x' => "post_{$freshPost->id}_shared_x",
        ];

        Log::info('Starting social share job', [
            'post_id' => $freshPost->id,
            'attempt' => $this->attempts(),
        ]);

        $errors = [];

        if (!App::isLocal()) {
            if (Cache::has($keys['telegram'])) {
                Log::info('Skipping Telegram (Already shared)', ['post_id' => $freshPost->id]);
            } else {
                try {
                    if (!$freshPost->option_one_image || !$freshPost->option_two_image) {
                        throw new Exception('Post missing required images');
                    }
                    if (!Storage::disk('public')->exists($freshPost->option_one_image) ||
                        !Storage::disk('public')->exists($freshPost->option_two_image)) {
                        throw new Exception('Post images do not exist in storage');
                    }

                    $telegramService->share($freshPost);

                    Cache::put($keys['telegram'], true, now()->addDay());
                    Log::info('Post shared to Telegram successfully');

                } catch (Throwable $e) {
                    $errors['telegram'] = $e->getMessage();
                    Log::error('Failed to share to Telegram', ['error' => $e->getMessage()]);
                }
            }
        } else {
            Log::info('Skipping Telegram share in local environment.');
        }

        if (Cache::has($keys['instagram'])) {
            Log::info('Skipping Instagram (Already shared)', ['post_id' => $freshPost->id]);
        } else {
            try {
                $instagramService->share($freshPost);

                Cache::put($keys['instagram'], true, now()->addDay());
                Log::info('Post shared to Instagram successfully');

            } catch (Throwable $e) {
                $errors['instagram'] = $e->getMessage();
                Log::error('Failed to share to Instagram', ['error' => $e->getMessage()]);
            }
        }

        if (Cache::has($keys['x'])) {
            Log::info('Skipping X (Already shared)', ['post_id' => $freshPost->id]);
        } else {
            try {
                $xService->share($freshPost);

                Cache::put($keys['x'], true, now()->addDay());
                Log::info('Post shared to X successfully');

            } catch (Throwable $e) {
                $errors['x'] = $e->getMessage();
                Log::error('Failed to share to X', ['error' => $e->getMessage()]);
            }
        }

        if (!empty($errors)) {
            Log::warning('Job finished with partial errors', [
                'post_id' => $freshPost->id,
                'errors' => $errors
            ]);
        } else {
            Log::info('Social share job completed successfully', ['post_id' => $freshPost->id]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SharePostToSocialMedia job failed permanently', [
            'post_id' => $this->post->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
