<?php

namespace App\Observers;

use App\Jobs\UpdateCommentScore;
use App\Models\Comment;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        UpdateCommentScore::dispatch($comment)->delay(now()->addSeconds(2));
    }

    public function deleted(Comment $comment): void
    {
        if ($comment->parent) {
            $comment->parent->refresh();
            UpdateCommentScore::dispatch($comment->parent);
        }
    }
}
