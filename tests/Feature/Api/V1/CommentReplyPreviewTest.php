<?php

namespace Tests\Feature\Api\V1;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommentReplyPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function thread(Post $post, int $replies): Comment
    {
        $author = User::factory()->create();
        $root = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

        for ($i = 1; $i <= $replies; $i++) {
            $reply = Comment::factory()->create([
                'post_id' => $post->id,
                'user_id' => $author->id,
                'parent_id' => $root->id,
                'root_comment_id' => $root->id,
                'content' => "reply {$i}",
            ]);
            // distinct, ordered timestamps
            Comment::whereKey($reply->id)->update(['created_at' => now()->subMinutes(100 - $i)]);
        }

        return $root;
    }

    public function test_each_root_gets_its_first_three_replies_in_the_established_order(): void
    {
        $post = Post::factory()->create();
        $a = $this->thread($post, 5);
        $b = $this->thread($post, 2);
        $c = $this->thread($post, 0);

        $data = collect($this->getJson("/api/v1/posts/{$post->id}/comments")->assertOk()->json('data'))->keyBy('id');

        // newest of the first three on top (reversed), as before the optimisation
        $this->assertSame(['reply 3', 'reply 2', 'reply 1'], collect($data[$a->id]['replies'])->pluck('content')->all());
        $this->assertSame(['reply 2', 'reply 1'], collect($data[$b->id]['replies'])->pluck('content')->all());
        $this->assertSame([], $data[$c->id]['replies']);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_comments(): void
    {
        $few = Post::factory()->create();
        $this->thread($few, 3);
        $many = Post::factory()->create();
        foreach (range(1, 12) as $_) {
            $this->thread($many, 3);
        }

        $count = function (Post $post): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/v1/posts/{$post->id}/comments")->assertOk();

            return count(DB::getQueryLog());
        };

        $this->assertSame($count($few), $count($many), 'comment listing is back to N+1');
    }

    public function test_the_viewers_own_likes_are_marked_on_replies(): void
    {
        $post = Post::factory()->create();
        $root = $this->thread($post, 2);
        $viewer = User::factory()->create();
        $reply = Comment::where('root_comment_id', $root->id)->orderBy('id')->first();
        DB::table('comment_likes')->insert(['user_id' => $viewer->id, 'comment_id' => $reply->id, 'created_at' => now(), 'updated_at' => now()]);

        $replies = collect($this->actingAs($viewer, 'sanctum')->getJson("/api/v1/posts/{$post->id}/comments")->json('data.0.replies'));

        $this->assertTrue((bool) $replies->firstWhere('id', $reply->id)['is_liked_by_current_user']);
        $this->assertFalse((bool) $replies->firstWhere('id', '!=', $reply->id)['is_liked_by_current_user']);
    }
}
