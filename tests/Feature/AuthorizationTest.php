<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function actors(): array
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $owner->id]);

        return [$owner, $other, $post, $comment];
    }

    public function test_api_strangers_cannot_edit_or_delete_posts(): void
    {
        [, $other, $post] = $this->actors();
        Sanctum::actingAs($other);

        $this->putJson("/api/v1/posts/{$post->id}", ['question' => 'hijacked?', 'option_one_title' => 'a', 'option_two_title' => 'b'])->assertForbidden();
        $this->deleteJson("/api/v1/posts/{$post->id}")->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'question' => $post->question]);
    }

    public function test_api_strangers_cannot_edit_comments_and_only_author_or_post_owner_can_delete(): void
    {
        [$owner, $other, $post, $comment] = $this->actors();
        $author = User::factory()->create();
        $theirs = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

        Sanctum::actingAs($other);
        $this->putJson("/api/v1/comments/{$comment->id}", ['content' => 'hijacked'])->assertForbidden();
        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => $comment->content]);

        // post owner may moderate, but never rewrite someone else's words
        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/comments/{$theirs->id}", ['content' => 'rewritten'])->assertForbidden();
        $this->deleteJson("/api/v1/comments/{$theirs->id}")->assertOk();
    }

    public function test_api_sessions_and_notifications_are_scoped_to_their_owner(): void
    {
        [$owner, $other] = $this->actors();
        $session = RefreshToken::create([
            'user_id' => $owner->id,
            'token' => hash('sha256', 'owner-session'),
            'expires_at' => now()->addDay(),
        ]);
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/auth/sessions/{$session->id}")->assertNotFound();
        $this->patchJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/notifications')->assertOk()->json('data'));

        $this->assertNull($session->fresh()->revoked_at);
        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_web_strangers_cannot_edit_or_delete_posts_and_comments(): void
    {
        [, $other, $post, $comment] = $this->actors();
        $this->actingAs($other);

        $this->get("/posts/{$post->id}/edit")->assertForbidden();
        $this->put("/posts/{$post->id}", ['question' => 'hijacked?'])->assertForbidden();
        $this->delete("/posts/{$post->id}")->assertForbidden();
        $this->putJson("/comments/{$comment->id}", ['content' => 'hijacked'])->assertForbidden();
        $this->deleteJson("/comments/{$comment->id}")->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => $comment->content]);
    }

    public function test_guests_cannot_reach_write_endpoints(): void
    {
        [, , $post, $comment] = $this->actors();

        $this->postJson("/api/v1/posts/{$post->id}/vote", ['option' => 1])->assertUnauthorized();
        $this->postJson("/api/v1/posts/{$post->id}/comments", ['content' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertUnauthorized();
        $this->putJson('/api/v1/me', ['first_name' => 'x'])->assertUnauthorized();
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->deleteJson("/posts/{$post->id}")->assertUnauthorized();
    }

    public function test_profile_update_ignores_privileged_fields(): void
    {
        [$owner] = $this->actors();
        $columns = ['email', 'is_developer', 'google_id', 'email_verified_at', 'ai_generations_daily_count'];
        $snapshot = fn () => (array) \Illuminate\Support\Facades\DB::table('users')->where('id', $owner->id)->first($columns);
        $before = $snapshot();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/me', [
            'first_name' => 'Fine',
            'email' => 'evil@example.com',
            'is_developer' => true,
            'google_id' => 'g-1',
            'email_verified_at' => null,
            'ai_generations_daily_count' => -999,
            'id' => 99999,
        ]);

        $this->assertSame($before, $snapshot());
    }
}
