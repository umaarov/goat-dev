<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Services\CommentScoringService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CalculateCommentScores extends Command
{
    protected $signature = 'comments:calculate-scores';
    protected $description = 'Calculate and update the DERS score for all existing comments';

    public function handle(CommentScoringService $scoringService): int
    {
        $this->info('Starting to calculate DERS scores for all existing comments...');

        $totalComments = Comment::count();
        if ($totalComments === 0) {
            $this->info('No comments found to score. Exiting.');
            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($totalComments);

        Comment::withCount(['likes', 'replies'])
            ->with('user', 'post.user')
            ->chunkById(200, function (Collection $comments) use ($scoringService, $progressBar) {
                foreach ($comments as $comment) {
                    $newScore = $scoringService->calculateScore($comment);
                    $comment->score = $newScore;
                    $comment->saveQuietly();
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);
        $this->info('✅ Successfully calculated and updated scores for all ' . $totalComments . ' comments.');

        return self::SUCCESS;
    }
}
