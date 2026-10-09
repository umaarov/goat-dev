<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\TagIndex;
use Illuminate\Console\Command;

class RebuildTags extends Command
{
    protected $signature = 'tags:rebuild';

    protected $description = 'Rebuild the tag pages index from the tags the questions carry';

    public function handle(): int
    {
        $count = 0;

        Post::query()->whereNotNull('ai_generated_tags')->chunkById(200, function ($posts) use (&$count) {
            foreach ($posts as $post) {
                TagIndex::sync($post);
                $count++;
            }
        });

        $this->info("Indexed tags of {$count} questions.");

        return self::SUCCESS;
    }
}
