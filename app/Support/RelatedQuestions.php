<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

// plain links from a question to others: the author's latest and the most voted, so crawlers (and people) have somewhere to go next
class RelatedQuestions
{
    private const EACH = 3;

    private const TTL = 600;

    public static function for(Post $post): array
    {
        return Cache::remember("related-questions:{$post->id}", self::TTL, function () use ($post) {
            $byAuthor = Post::with('user:id,username')->where('user_id', $post->user_id)->where('id', '!=', $post->id)
                ->latest()->limit(self::EACH)->get();

            $popular = Post::with('user:id,username')->whereNotIn('id', $byAuthor->pluck('id')->push($post->id))
                ->orderByDesc('total_votes')->latest()->limit(self::EACH)->get();

            return $byAuthor->concat($popular)->map(fn (Post $p) => [
                'question' => $p->question,
                'votes' => (int) $p->total_votes,
                'url' => route('posts.show.user-scoped', ['username' => $p->user->username, 'post' => $p->id]),
            ])->values()->all();
        });
    }
}
