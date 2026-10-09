<?php

namespace App\Console\Commands;

use App\Mail\QuestionMilestone;
use App\Models\Post;
use App\Services\Promotion\Promoter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class PromoteQuestions extends Command
{
    protected $signature = 'promote:run {kind : daily or milestone} {--dry-run : only show what would happen}';

    protected $description = 'Post the question of the day, or announce vote milestones and tell their authors';

    public function handle(Promoter $promoter): int
    {
        $dry = (bool) $this->option('dry-run');

        if (!$dry && !config('promotion.enabled')) {
            $this->warn('Promotion is off (PROMOTION_ENABLED).');

            return self::SUCCESS;
        }

        return match ($this->argument('kind')) {
            'daily' => $this->daily($promoter, $dry),
            'milestone' => $this->milestones($promoter, $dry),
            default => self::INVALID,
        };
    }

    private function daily(Promoter $promoter, bool $dry): int
    {
        if (!$dry && $promoter->remainingToday() === 0) {
            $this->info('Daily cap reached.');

            return self::SUCCESS;
        }

        $post = $promoter->dailyCandidate();

        if (!$post) {
            $this->info('No question qualifies.');

            return self::SUCCESS;
        }

        $this->info("Question of the day: #{$post->id} {$post->question}");

        if ($dry) {
            $this->line($promoter->caption($post, 'telegram', Promoter::DAILY));
        } else {
            $promoter->record($post, Promoter::DAILY);
        }

        return self::SUCCESS;
    }

    private function milestones(Promoter $promoter, bool $dry): int
    {
        foreach ($promoter->milestoneCandidates() as [$post, $milestone]) {
            $this->info("Milestone {$milestone}: #{$post->id} {$post->question}");

            if ($dry) {
                $this->line($promoter->caption($post, 'telegram', Promoter::MILESTONE, $milestone));

                continue;
            }

            $promoter->record($post, Promoter::MILESTONE, $milestone, $promoter->remainingToday() > 0);
            $this->tellAuthor($post, $milestone);
        }

        return self::SUCCESS;
    }

    private function tellAuthor(Post $post, int $milestone): void
    {
        $author = $post->user;

        if ($author && !$author->trashed() && $author->email && $author->receives_notifications) {
            Mail::to($author)->queue(new QuestionMilestone($author, $post, $milestone));
        }
    }
}
