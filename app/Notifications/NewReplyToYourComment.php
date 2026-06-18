<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Messages\FcmMessage;
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
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return FcmMessage::create(
            title: 'New reply',
            body: "@{$this->replier->username} replied: ".Str::limit($this->reply->content, 80),
            data: [
                'type' => 'comment_reply',
                'reply_id' => $this->reply->id,
                'post_id' => $this->reply->post_id,
                'root_comment_id' => $this->reply->root_comment_id ?? '',
            ],
        );
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
