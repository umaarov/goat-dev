<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// first N replies of every root comment in ONE query (was one query set per comment)
final class CommentReplyPreview
{
    /**
     * @param  Collection<int, Comment>  $roots
     * @param  callable(Comment):void|null  $afterLoad  runs on each loaded reply
     */
    public static function attach(Collection $roots, ?int $userId, string $userColumns, ?callable $afterLoad = null, int $limit = 3): void
    {
        if ($roots->isEmpty()) {
            return;
        }

        $ranked = Comment::query()
            ->select('id', DB::raw('ROW_NUMBER() OVER (PARTITION BY root_comment_id ORDER BY created_at ASC, id ASC) AS reply_rank'))
            ->whereIn('root_comment_id', $roots->pluck('id'));

        $query = Comment::query()
            ->whereIn('id', fn ($q) => $q->select('id')->fromSub($ranked, 'ranked')->where('reply_rank', '<=', $limit))
            ->withCount('likes')
            ->with("user:{$userColumns}", 'parent:id,user_id', 'parent.user:id,username')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if ($userId) {
            $query->with(['likes' => fn ($q) => $q->where('user_id', $userId)]);
        }

        $byRoot = $query->get()->each($afterLoad ?? fn () => null)->groupBy('root_comment_id');

        $roots->each(fn (Comment $root) => $root->setRelation(
            'flatReplies',
            ($byRoot[$root->id] ?? collect())->reverse()->values()
        ));
    }
}
