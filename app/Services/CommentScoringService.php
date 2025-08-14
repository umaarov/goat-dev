<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CommentScoringService
{
    private array $weights;
    private array $roles;

    public function __construct()
    {
        $this->weights = Config::get('ders.weights');
        $this->roles = Config::get('ders.roles');
    }
    public function calculateScore(Comment $comment): float
    {
        $comment->loadMissing('user', 'post.user');

        // --- 1. Base Engagement Score ---
        $likeScore = ($comment->likes_count ?? 0) * $this->weights['like'];
        $replyScore = ($comment->replies_count ?? 0) * $this->weights['reply'];
        $engagementScore = $likeScore + $replyScore;

        // --- 2. Time Decay Score ---
        $hoursElapsed = abs(now()->diffInHours($comment->created_at, false));
        // Formula: S_time = W_time * e^(-λ * Δt)
        $timeDecayScore = $this->weights['time_multiplier'] * exp(-$this->weights['decay_rate_lambda'] * $hoursElapsed);

        // --- 3. Contextual & Social Boosts ---
        $socialBoost = 0;
        if ($comment->user_id === $comment->post->user_id) {
            $socialBoost += $this->weights['post_author'];
        }
        if (in_array($comment->user->username, $this->roles['moderators'])) {
            $socialBoost += $this->weights['moderator'];
        } elseif (in_array($comment->user->username, $this->roles['verified'])) {
            $socialBoost += $this->weights['verified_user'];
        }

        // --- 4. Final Score Calculation ---
        $totalScore = $engagementScore + $timeDecayScore + $socialBoost;

        return round($totalScore, 4);
    }

    public function updateCommentAndParentScores(Comment $comment): void
    {
        $newScore = $this->calculateScore($comment);
        $comment->score = $newScore;
        $comment->saveQuietly();

        if ($comment->parent) {
            $comment->parent->loadMissing('user', 'post.user');
            $parentScore = $this->calculateScore($comment->parent);
            $comment->parent->score = $parentScore;
            $comment->parent->saveQuietly();
        }
    }
}
