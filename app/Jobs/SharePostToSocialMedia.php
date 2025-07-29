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
            Log::error('Post not found in social media sharing job', [
                'original_post_id' => $this->post->id,
                'job_id' => $this->job?->getJobId()
            ]);
            return;
        }

        Log::info('Starting social share job', [
            'post_id' => $freshPost->id,
            'job_id' => $this->job?->getJobId(),
            'attempt' => $this->attempts(),
            'post_data' => [
                'option_one_image' => $freshPost->option_one_image,
                'option_two_image' => $freshPost->option_two_image,
                'question' => $freshPost->question,
                'option_one_title' => $freshPost->option_one_title,
                'option_two_title' => $freshPost->option_two_title,
            ],
            'storage_check' => [
                'option_one_exists' => $freshPost->option_one_image ? Storage::disk('public')->exists($freshPost->option_one_image) : false,
                'option_two_exists' => $freshPost->option_two_image ? Storage::disk('public')->exists($freshPost->option_two_image) : false,
            ]
        ]);

        $errors = [];

        // --- Share to Telegram ---
        if (App::environment() !== 'local') {
            try {
                if (!$freshPost->option_one_image || !$freshPost->option_two_image) {
                    throw new Exception('Post missing required images for Telegram sharing');
                }

                if (!Storage::disk('public')->exists($freshPost->option_one_image) ||
                    !Storage::disk('public')->exists($freshPost->option_two_image)) {
                    throw new Exception('Post images do not exist in storage');
                }

                $telegramService->share($freshPost);
                Log::info('Post shared to Telegram successfully', ['post_id' => $freshPost->id]);
            } catch (Throwable $e) {
                $errors['telegram'] = $e->getMessage();
                Log::error('Failed to share post to Telegram', [
                    'post_id' => $freshPost->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'attempt' => $this->attempts()
                ]);
            }
        } else {
            Log::info('Skipping Telegram share in local environment.', ['post_id' => $freshPost->id]);
        }

        try {
            $instagramService->share($freshPost);
            Log::info('Post shared to Instagram successfully', ['post_id' => $freshPost->id]);
        } catch (Throwable $e) {
            $errors['instagram'] = $e->getMessage();
            Log::error('Failed to share post to Instagram', [
                'post_id' => $freshPost->id,
                'error' => $e->getMessage()
            ]);
        }

        // --- Share to X/Twitter ---
        try {
            $xService->share($freshPost);
            Log::info('Post shared to X successfully', ['post_id' => $freshPost->id]);
        } catch (Throwable $e) {
            $errors['x'] = $e->getMessage();
            Log::error('Failed to share post to X', [
                'post_id' => $freshPost->id,
                'error' => $e->getMessage()
            ]);
        }

        if (count($errors) === 3) {
            throw new Exception('All social media sharing failed: ' . json_encode($errors));
        }

        if (!empty($errors)) {
            Log::warning('Partial social media sharing failure', [
                'post_id' => $freshPost->id,
                'failed_services' => array_keys($errors),
                'errors' => $errors
            ]);
        }

        Log::info('Social share job completed', [
            'post_id' => $freshPost->id,
            'successful_services' => 3 - count($errors),
            'failed_services' => count($errors)
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SharePostToSocialMedia job failed permanently', [
            'post_id' => $this->post->id,
            'error' => $exception->getMessage(),
            'job_id' => $this->job?->getJobId(),
            'max_attempts_reached' => true
        ]);
    }
}
