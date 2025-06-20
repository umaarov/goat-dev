<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommentLiked extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $liker;
    protected Comment $comment;

    public function __construct(User $liker, Comment $comment)
    {
        $this->liker = $liker;
        $this->comment = $comment;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'liker_id' => $this->liker->id,
            'liker_name' => $this->liker->username,
            'comment_id' => $this->comment->id,
            'comment_content' => Str::limit($this->comment->content, 50),
            'post_id' => $this->comment->post_id,
        ];
    }
}
