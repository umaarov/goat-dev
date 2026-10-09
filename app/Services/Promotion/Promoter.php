<?php

namespace App\Services\Promotion;

use App\Jobs\PromotePostToSocialMedia;
use App\Models\Post;
use App\Models\SocialPromotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Promoter
{
    public const DAILY = 'daily';

    public const MILESTONE = 'milestone';

    // best questions first, none repeated inside the cooldown, only ones with both pictures and a few votes
    public function dailyCandidate(): ?Post
    {
        return Post::query()
            ->whereNotNull('option_one_image')
            ->whereNotNull('option_two_image')
            ->where('total_votes', '>=', 5)
            ->whereDoesntHave('promotions', fn ($q) => $q->where('created_at', '>', now()->subDays(config('promotion.cooldown_days'))))
            ->withCount('comments')
            ->orderByRaw('total_votes + comments_count * 3 desc')
            ->orderBy('id')
            ->first();
    }

    // [post, milestone] pairs: the highest milestone a still-active question has crossed and not announced yet
    public function milestoneCandidates(): Collection
    {
        $milestones = collect(config('promotion.milestones'))->sortDesc()->values();

        return Post::query()
            ->where('total_votes', '>=', $milestones->last())
            ->whereHas('votes', fn ($q) => $q->where('created_at', '>', now()->subHours(6)))
            ->orderByDesc('total_votes')
            ->limit(50)
            ->get()
            ->map(function (Post $post) use ($milestones) {
                $milestone = (int) $milestones->first(fn ($m) => $post->total_votes >= $m);

                return $post->promotions()->where('kind', self::MILESTONE)->where('milestone', $milestone)->exists()
                    ? null
                    : [$post, $milestone];
            })
            ->filter()
            ->values();
    }

    public function remainingToday(): int
    {
        $sent = SocialPromotion::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotNull('networks')
            ->count();

        return max(0, config('promotion.daily_cap') - $sent);
    }

    public function record(Post $post, string $kind, int $milestone = 0, bool $publish = true): SocialPromotion
    {
        $promotion = SocialPromotion::create([
            'post_id' => $post->id,
            'kind' => $kind,
            'milestone' => $milestone,
            'networks' => $publish ? [] : null,
        ]);

        if ($publish) {
            PromotePostToSocialMedia::dispatch($promotion->id);
        }

        return $promotion;
    }

    public function url(Post $post, string $network, string $kind): string
    {
        $post->loadMissing('user');

        return route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id])
            .'?'.http_build_query(['utm_source' => $network, 'utm_medium' => 'social', 'utm_campaign' => $kind]);
    }

    public function caption(Post $post, string $network, string $kind, int $milestone = 0): string
    {
        $one = $this->percent($post->option_one_percentage);
        $two = $this->percent($post->option_two_percentage);
        $votes = number_format((int) $post->total_votes);

        $headline = match (true) {
            $kind === self::MILESTONE => "📊 {$votes} votes are in!",
            $post->created_at && $post->created_at->lt(now()->subDays(30)) => '↩️ Still dividing the crowd',
            default => '🔥 Question of the day',
        };

        $question = Str::limit(trim($post->question), $network === 'x' ? 110 : 300);
        $results = "🟦 {$post->option_one_title} — {$one}\n🟥 {$post->option_two_title} — {$two}";
        $cta = match ($network) {
            'instagram' => '👉 Pick your side on goat.uz (link in bio)',
            default => '👉 Pick your side: '.$this->url($post, $network, $kind),
        };

        $caption = "{$headline}\n\n⚡️ {$question}\n\n{$results}";

        if ($network === 'instagram') {
            $caption .= "\n{$votes} votes\n\n{$cta}\n\n#goatuz #thisorthat #poll";
        } elseif ($network === 'x') {
            // 280 characters, a link always counts as 23
            $caption = Str::limit("{$headline}\n\n⚡️ {$question}\n\n🟦 ".Str::limit($post->option_one_title, 30)." {$one} · 🟥 ".Str::limit($post->option_two_title, 30)." {$two}", 230)."\n\n{$cta}";
        } else {
            $caption .= "\n{$votes} votes\n\n{$cta}";
        }

        return $caption;
    }

    private function percent(float|int $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }
}
