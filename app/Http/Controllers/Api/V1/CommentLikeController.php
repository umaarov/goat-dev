<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Notifications\CommentLiked;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CommentLikeController extends ApiController
{
    /**
     * POST /comments/{comment}/like — toggle like.
     */
    public function toggle(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        $owner = $comment->user;
        $notify = $owner && $owner->id !== $user->id;
        $hasCountColumn = Schema::hasColumn('comments', 'likes_count');

        DB::beginTransaction();
        try {
            $existing = CommentLike::where('user_id', $user->id)
                ->where('comment_id', $comment->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                if ($hasCountColumn) {
                    $comment->decrement('likes_count');
                }
                $isLiked = false;
            } else {
                CommentLike::create(['user_id' => $user->id, 'comment_id' => $comment->id]);
                if ($hasCountColumn) {
                    $comment->increment('likes_count');
                }
                $isLiked = true;

                if ($notify) {
                    $owner->notify(new CommentLiked($user, $comment));
                }
            }

            DB::commit();

            $likesCount = $hasCountColumn ? $comment->refresh()->likes_count : $comment->likes()->count();

            return $this->ok([
                'is_liked' => $isLiked,
                'likes_count' => (int) $likesCount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API toggle comment like failed: '.$e->getMessage(), [
                'user_id' => $user->id,
                'comment_id' => $comment->id,
            ]);

            return $this->error(__('messages.error_generic_server_error'), 500, 'server_error');
        }
    }
}
