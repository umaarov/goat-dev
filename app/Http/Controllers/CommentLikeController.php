<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentLike;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CommentLikeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function toggleLike(Request $request, Comment $comment)
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $existingLike = CommentLike::where('user_id', $user->id)
                ->where('comment_id', $comment->id)
                ->first();

            $isLiked = false;
            $messageUser = '';

            if ($existingLike) {
                $existingLike->delete();
                if (Schema::hasColumn('comments', 'likes_count')) {
                    $comment->decrement('likes_count');
                }
                $isLiked = false;
                $messageUser = __('messages.comment_unliked_successfully');
            } else {
                CommentLike::create([
                    'user_id' => $user->id,
                    'comment_id' => $comment->id,
                ]);
                if (Schema::hasColumn('comments', 'likes_count')) {
                    $comment->increment('likes_count');
                }
                $isLiked = true;
                $messageUser = __('messages.comment_liked_successfully');
            }

            DB::commit();

            $currentLikesCount = Schema::hasColumn('comments', 'likes_count')
                ? $comment->refresh()->likes_count
                : $comment->likes()->count();


            return response()->json([
                'is_liked' => $isLiked,
                'likes_count' => $currentLikesCount,
                'message_user' => $messageUser,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error toggling comment like: " . $e->getMessage(), [
                'user_id' => $user->id,
                'comment_id' => $comment->id,
                'exception' => $e
            ]);
            return response()->json(['message' => __('messages.error_generic_server_error'), 'error_dev' => $e->getMessage()], 500);
        }
    }
}
