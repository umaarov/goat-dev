<?php

namespace App\Events;

use App\Models\Comment;
use App\Services\CommentScoringService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tries = 3;
    public $timeout = 20;

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
        $this->comment->load('user:id,username,profile_picture', 'post:id,user_id', 'parent.user');
        $this->comment->loadCount(['likes', 'replies']);
        $scoringService = new CommentScoringService();
        $this->comment->score = $scoringService->calculateScore($this->comment);

        return [
            'comment' => $this->comment,
        ];
    }
}
