<?php
// app/Events/NewCommentPosted.php
// [NEW FILE]

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Comment $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }

    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel('post.' . $this->comment->post_id);
    }

    public function broadcastAs(): string
    {
        return 'NewCommentPosted';
    }

    public function broadcastWith(): array
    {
        return [
            'comment' => $this->comment->load('user:id,username,profile_picture', 'parent.user'),
        ];
    }
}
