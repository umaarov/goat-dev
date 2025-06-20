<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewReplyToYourComment extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $replier;
    protected Comment $reply;

    public function __construct(User $replier, Comment $reply)
    {
        $this->replier = $replier;
        $this->reply = $reply;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'replier_id' => $this->replier->id,
            'replier_name' => $this->replier->username,
            'reply_id' => $this->reply->id,
            'reply_content' => Str::limit($this->reply->content, 50),
            'post_id' => $this->reply->post_id,
            'root_comment_id' => $this->reply->root_comment_id,
        ];
    }
}
