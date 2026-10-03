<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\PostCardImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

// public link-preview image of a question at /@username/post/ID/card.jpg; the ?v= changes with the votes, so it can be cached for good
class PostCardController extends Controller
{
    public function __invoke(string $username, Post $post, PostCardImage $cards): Response|RedirectResponse
    {
        // same rule as the question page: one canonical address per question
        if ($post->user->username !== $username) {
            return redirect()->route('posts.card', ['username' => $post->user->username, 'post' => $post->id] + request()->query(), 301);
        }

        $disk = Storage::disk('public');
        $path = "cards/{$post->id}-{$cards->version($post)}.jpg";

        if (!$disk->exists($path)) {
            // image drawing costs CPU: cap how many cards are made per minute, whoever asks
            abort_unless(RateLimiter::attempt('post-card-render', 600, fn () => true, 60), 503, 'Try again shortly.');

            Cache::lock("post-card:{$post->id}", 15)->block(10, function () use ($disk, $path, $post, $cards) {
                if ($disk->exists($path)) {
                    return;
                }
                $disk->put($path, $cards->render($post));
                // older versions of this card are never asked for again
                foreach ($disk->files('cards') as $old) {
                    if ($old !== $path && str_starts_with(basename($old), "{$post->id}-")) {
                        $disk->delete($old);
                    }
                }
            });
        }

        return response($disk->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
