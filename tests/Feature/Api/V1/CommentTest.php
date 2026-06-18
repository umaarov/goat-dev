<?php

namespace Tests\Feature\Api\V1;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_comment_and_reply(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $parent = $this->postJson("/api/v1/posts/{$post->id}/comments", ['content' => 'Top level'])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', null)
            ->json('data.id');

        $this->postJson("/api/v1/posts/{$post->id}/comments", [
            'content' => 'A reply',
            'parent_id' => $parent,
        ])->assertCreated()
            ->assertJsonPath('data.parent_id', $parent)
            ->assertJsonPath('data.root_comment_id', $parent);
    }

    public function test_list_comments_with_reply_preview(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $root = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $user->id]);
        Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $root->id,
            'root_comment_id' => $root->id,
        ]);

        $this->getJson("/api/v1/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.replies_count', 1)
            ->assertJsonCount(1, 'data.0.replies');
    }

    public function test_only_owner_can_update_comment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($other);
        $this->putJson("/api/v1/comments/{$comment->id}", ['content' => 'hacked'])
            ->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/comments/{$comment->id}", ['content' => 'edited'])
            ->assertOk()
            ->assertJsonPath('data.content', 'edited');
    }

    public function test_post_owner_can_delete_any_comment(): void
    {
        $postOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $postOwner->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $commenter->id]);

        Sanctum::actingAs($postOwner);
        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertOk();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_toggle_like(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/comments/{$comment->id}/like")
            ->assertOk()
            ->assertJsonPath('data.is_liked', true)
            ->assertJsonPath('data.likes_count', 1);

        $this->postJson("/api/v1/comments/{$comment->id}/like")
            ->assertOk()
            ->assertJsonPath('data.is_liked', false)
            ->assertJsonPath('data.likes_count', 0);
    }
}
