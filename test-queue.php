<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Queue;
use App\Jobs\SharePostToSocialMedia;
use App\Models\Post;

$testPost = Post::first();

if ($testPost) {
    SharePostToSocialMedia::dispatch($testPost);
    echo "Job dispatched for Post ID: " . $testPost->id . "\n";
} else {
    echo "No posts found. Create a test post first.\n";
}
