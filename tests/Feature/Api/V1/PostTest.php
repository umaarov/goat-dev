<?php

namespace Tests\Feature\Api\V1;

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_is_public_and_paginated(): void
    {
        Post::factory()->count(3)->create();

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total', 'has_more_pages']]);
    }

    public function test_show_includes_user_vote_for_authenticated_caller(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['total_votes' => 1, 'option_one_votes' => 1]);
        Vote::create(['user_id' => $user->id, 'post_id' => $post->id, 'vote_option' => 'option_one']);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.user_vote', 'option_one');
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/v1/posts', [])->assertUnauthorized();
    }

    public function test_create_post_with_images(): void
    {
        Storage::fake('public');
        Event::fake([PostCreated::class]);
        Queue::fake();
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/posts', [
            'question' => 'Tea or coffee?',
            'option_one_title' => 'Tea',
            'option_two_title' => 'Coffee',
            'option_one_image' => UploadedFile::fake()->image('one.jpg', 80, 80),
            'option_two_image' => UploadedFile::fake()->image('two.jpg', 80, 80),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.question', 'Tea or coffee?')
            ->assertJsonPath('data.option_one.title', 'Tea');

        $this->assertDatabaseHas('posts', ['question' => 'Tea or coffee?', 'user_id' => $user->id]);
        Event::assertDispatched(PostCreated::class);
    }

    public function test_create_post_validation_fails_without_images(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/posts', [
            'question' => 'Q?',
            'option_one_title' => 'A',
            'option_two_title' => 'B',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['option_one_image', 'option_two_image']);
    }

    public function test_vote_then_duplicate_returns_conflict(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/posts/{$post->id}/vote", ['option' => 'option_one'])
            ->assertOk()
            ->assertJsonPath('data.total_votes', 1)
            ->assertJsonPath('data.user_vote', 'option_one');

        $this->postJson("/api/v1/posts/{$post->id}/vote", ['option' => 'option_two'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'already_voted');
    }

    public function test_non_owner_cannot_delete_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->deleteJson("/api/v1/posts/{$post->id}")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'access_forbidden');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
    }
}
