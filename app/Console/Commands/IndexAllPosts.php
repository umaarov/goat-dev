<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\GoatSearchClient;
use Illuminate\Console\Command;

class IndexAllPosts extends Command
{
    protected $signature = 'search:index-all';
    protected $description = 'Push all posts to the C++ Goat Search Engine';

    public function handle(GoatSearchClient $client)
    {
        $this->info("Starting indexing...");

        Post::chunk(100, function ($posts) use ($client) {
            foreach ($posts as $post) {
                $content = $post->question . ' ' .
                    $post->option_one_title . ' ' .
                    $post->option_two_title . ' ' .
                    ($post->ai_generated_tags ?? '');

                $client->index($post->id, $content);
                $this->output->write('.');
            }
        });

        $client->save();
        $this->info("\nDone! Engine saved.");
    }
}
