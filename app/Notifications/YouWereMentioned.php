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

class YouWereMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $mentioner;

    protected Comment $comment;

    public function __construct(User $mentioner, Comment $comment)
    {
        $this->mentioner = $mentioner;
        $this->comment = $comment;
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return FcmMessage::create(
            title: 'You were mentioned',
            body: "@{$this->mentioner->username} mentioned you: ".Str::limit($this->comment->content, 80),
            data: [
                'type' => 'mention',
                'comment_id' => $this->comment->id,
                'post_id' => $this->comment->post_id,
            ],
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'mentioner_id' => $this->mentioner->id,
            'mentioner_name' => $this->mentioner->username,
            'comment_id' => $this->comment->id,
            'comment_content' => Str::limit($this->comment->content, 50),
            'post_id' => $this->comment->post_id,
        ];
    }
}
