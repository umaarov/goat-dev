<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Services\CommentScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateCommentScore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(public Comment $comment)
    {
        $this->onQueue('scoring');
    }

    public function handle(CommentScoringService $scoringService): void
    {
        $scoringService->updateCommentAndParentScores($this->comment);
    }
}
