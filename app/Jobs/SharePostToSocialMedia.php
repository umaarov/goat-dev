<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\InstagramService;
use App\Services\TelegramService;
use App\Services\XService;
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

    public int $tries = 1;
    public int $timeout = 180;

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
        $masterLockKey = "job_lock_share_post_{$this->post->id}";

        if (Cache::has($masterLockKey)) {
            Log::warning("Job cancelled: detected duplicate execution attempt for Post {$this->post->id}");
            $this->delete();
            return;
        }

        Cache::put($masterLockKey, true, 3600);

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

        Log::info('Starting social share job (One-Shot Mode)', ['post_id' => $freshPost->id]);

        if (!App::isLocal()) {
            if (!Cache::has($keys['telegram'])) {
                try {
                    if ($freshPost->option_one_image &&
                        $freshPost->option_two_image &&
                        Storage::disk('public')->exists($freshPost->option_one_image) &&
                        Storage::disk('public')->exists($freshPost->option_two_image)) {

                        $telegramService->share($freshPost);
                        Cache::put($keys['telegram'], true, now()->addDay());
                        Log::info('Post shared to Telegram successfully');
                    }
                } catch (Throwable $e) {
                    Log::error('Telegram Share Failed (Skipping)', ['error' => $e->getMessage()]);
                }
            }
        }

        if (!Cache::has($keys['instagram'])) {
            try {
                $instagramService->share($freshPost);
                Cache::put($keys['instagram'], true, now()->addDay());
                Log::info('Post shared to Instagram successfully');
            } catch (Throwable $e) {
                Log::error('Instagram Share Failed (Skipping)', ['error' => $e->getMessage()]);
            }
        }

        if (!Cache::has($keys['x'])) {
            try {
                $xService->share($freshPost);
                Cache::put($keys['x'], true, now()->addDay());
                Log::info('Post shared to X successfully');
            } catch (Throwable $e) {
                Log::error('X Share Failed (Skipping)', ['error' => $e->getMessage()]);
            }
        }

        Log::info('Social share job finished.');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SharePostToSocialMedia job failed permanently', [
            'post_id' => $this->post->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
