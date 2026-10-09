<?php

namespace App\Jobs;

use App\Models\SocialPromotion;
use App\Services\InstagramService;
use App\Services\Promotion\Promoter;
use App\Services\TelegramService;
use App\Services\XService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PromotePostToSocialMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $promotionId)
    {
    }

    public function handle(Promoter $promoter, TelegramService $telegram): void
    {
        $promotion = SocialPromotion::with('post.user')->find($this->promotionId);
        $post = $promotion?->post;

        if (!$post) {
            return;
        }

        $sent = $promotion->networks ?? [];
        $hasImages = $post->option_one_image && $post->option_two_image
            && Storage::disk('public')->exists($post->option_one_image)
            && Storage::disk('public')->exists($post->option_two_image);

        $targets = [
            'telegram' => fn () => $hasImages ? $telegram->share($post, $promoter->caption($post, 'telegram', $promotion->kind, $promotion->milestone)) : null,
            'instagram' => fn () => app(InstagramService::class)->share($post, $promoter->caption($post, 'instagram', $promotion->kind, $promotion->milestone)),
            'x' => fn () => app(XService::class)->share($post, $promoter->caption($post, 'x', $promotion->kind, $promotion->milestone)),
        ];

        foreach ($targets as $network => $send) {
            if (in_array($network, $sent, true)) {
                continue;
            }

            try {
                $send();
                $sent[] = $network;
            } catch (Throwable $e) {
                Log::error("Promotion to {$network} failed", ['post_id' => $post->id, 'kind' => $promotion->kind, 'error' => $e->getMessage()]);
            }
        }

        $promotion->update(['networks' => $sent]);
        Log::info('[PROMOTION] done', ['post_id' => $post->id, 'kind' => $promotion->kind, 'milestone' => $promotion->milestone, 'networks' => $sent]);
    }
}
