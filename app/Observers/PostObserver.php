<?php
namespace App\Observers;

use App\Models\Post;
use App\Services\CppSearchService;

class PostObserver
{
    public function saved(Post $post): void
    {
        (new CppSearchService())->indexPost($post);
    }
}
