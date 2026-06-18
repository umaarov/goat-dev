<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RatingController extends ApiController
{
    use ResolvesMediaUrls;

    private const LIMIT = 25;

    /**
     * GET /ratings — community leaderboards.
     */
    public function index(): JsonResponse
    {
        return $this->ok([
            'top_by_post_votes' => $this->topByPostVotes(),
            'top_by_post_count' => $this->topByPostCount(),
            'top_by_comment_count' => $this->topByCommentCount(),
            'top_by_comment_likes' => $this->topByCommentLikes(),
        ]);
    }

    private function baseUserColumns(): array
    {
        return ['users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture'];
    }

    private function format($rows, string $scoreKey): array
    {
        return $rows->map(fn ($u) => [
            'id' => $u->id,
            'username' => $u->username,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'profile_picture' => $this->mediaUrl($u->profile_picture),
            'score' => (int) $u->{$scoreKey},
        ])->all();
    }

    private function topByPostVotes(): array
    {
        $rows = DB::table('users')
            ->select(array_merge($this->baseUserColumns(), [DB::raw('SUM(posts.total_votes) as score')]))
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->groupBy($this->baseUserColumns())
            ->havingRaw('SUM(posts.total_votes) > 0')
            ->orderByDesc('score')
            ->take(self::LIMIT)
            ->get();

        return $this->format($rows, 'score');
    }

    private function topByPostCount(): array
    {
        $rows = DB::table('users')
            ->select(array_merge($this->baseUserColumns(), [DB::raw('COUNT(posts.id) as score')]))
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->groupBy($this->baseUserColumns())
            ->orderByDesc('score')
            ->take(self::LIMIT)
            ->get();

        return $this->format($rows, 'score');
    }

    private function topByCommentCount(): array
    {
        $rows = DB::table('users')
            ->select(array_merge($this->baseUserColumns(), [DB::raw('COUNT(comments.id) as score')]))
            ->join('comments', 'users.id', '=', 'comments.user_id')
            ->groupBy($this->baseUserColumns())
            ->orderByDesc('score')
            ->take(self::LIMIT)
            ->get();

        return $this->format($rows, 'score');
    }

    private function topByCommentLikes(): array
    {
        $rows = DB::table('users')
            ->select(array_merge($this->baseUserColumns(), [DB::raw('SUM(comments.likes_count) as score')]))
            ->join('comments', 'users.id', '=', 'comments.user_id')
            ->where('comments.likes_count', '>', 0)
            ->groupBy($this->baseUserColumns())
            ->orderByDesc('score')
            ->take(self::LIMIT)
            ->get();

        return $this->format($rows, 'score');
    }
}
