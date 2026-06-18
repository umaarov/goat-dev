<?php

namespace Tests\Feature\Api\V1;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\CommentLiked;
use App\Notifications\NewReplyToYourComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_unread_count_and_read_all(): void
    {
        $user = User::factory()->create();
        $liker = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $user->notify(new CommentLiked($liker, $comment));

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 1);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_reply_dispatches_database_and_push_channels(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $replier = User::factory()->create();
        $post = Post::factory()->create();
        $rootComment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

        Sanctum::actingAs($replier);

        $this->postJson("/api/v1/posts/{$post->id}/comments", [
            'content' => 'Replying to you',
            'parent_id' => $rootComment->id,
        ])->assertCreated();

        Notification::assertSentTo(
            $author,
            NewReplyToYourComment::class,
            fn ($notification, $channels) => in_array('database', $channels) && in_array(FcmChannel::class, $channels)
        );
    }
}
