<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RatingService
{

    private const BADGE_RANK_THRESHOLD = 3; //Only users in the top 3 will get a badge.

    public function getUserBadges(User $user): array
    {
        $badges = [];

        // 1. The Gilded Horn - Most votes on posts
        $votesRank = $this->getUserRankForPostVotes($user);
        if ($votesRank && $votesRank <= self::BADGE_RANK_THRESHOLD) {
            $badges['votes'] = [
                'name' => 'The Gilded Horn',
                'rank' => $votesRank,
                'classes' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            ];
        }

        // 2. The Creator's Quill - Most posts created
        $postCountRank = $this->getUserRankForPostCount($user);
        if ($postCountRank && $postCountRank <= self::BADGE_RANK_THRESHOLD) {
            $badges['posters'] = [
                'name' => 'The Creator\'s Quill',
                'rank' => $postCountRank,
                'classes' => 'bg-gray-200 text-gray-800 border-gray-400',
            ];
        }

        // 3. Heart of the Community - Most likes on comments
        $commentLikesRank = $this->getUserRankForCommentLikes($user);
        if ($commentLikesRank && $commentLikesRank <= self::BADGE_RANK_THRESHOLD) {
            $badges['likes'] = [
                'name' => 'Heart of the Community',
                'rank' => $commentLikesRank,
                'classes' => 'bg-red-100 text-red-800 border-red-300',
            ];
        }

        // 4. The Dialogue Weaver - Most comments
        $commentCountRank = $this->getUserRankForCommentCount($user);
        if ($commentCountRank && $commentCountRank <= self::BADGE_RANK_THRESHOLD) {
            $badges['commentators'] = [
                'name' => 'The Dialogue Weaver',
                'rank' => $commentCountRank,
                'classes' => 'bg-blue-100 text-blue-800 border-blue-300',
            ];
        }

        return $badges;
    }

    private function getUserRankForPostVotes(User $user): ?int
    {
        $userScore = $user->posts()->sum('total_votes');
        if ($userScore == 0) {
            return null;
        }

        $rank = DB::table('posts')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('SUM(total_votes) > ?', [$userScore])
            ->get()
            ->count();

        return $rank + 1;
    }

    private function getUserRankForPostCount(User $user): ?int
    {
        $userScore = $user->posts_count;
        if ($userScore == 0) {
            return null;
        }

        $rank = DB::table('posts')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(id) > ?', [$userScore])
            ->get()
            ->count();

        return $rank + 1;
    }

    private function getUserRankForCommentLikes(User $user): ?int
    {
        $userScore = $user->comments()->sum('likes_count');
        if ($userScore == 0) {
            return null;
        }

        $rank = DB::table('comments')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('SUM(likes_count) > ?', [$userScore])
            ->get()
            ->count();

        return $rank + 1;
    }

    private function getUserRankForCommentCount(User $user): ?int
    {
        $userScore = $user->comments()->count();
        if ($userScore == 0) {
            return null;
        }

        $rank = DB::table('comments')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(id) > ?', [$userScore])
            ->get()
            ->count();

        return $rank + 1;
    }
}
