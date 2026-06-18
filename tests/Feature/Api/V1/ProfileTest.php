<?php

namespace Tests\Feature\Api\V1;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_returns_stats(): void
    {
        $user = User::factory()->create(['username' => 'profileowner']);
        Post::factory()->count(2)->create(['user_id' => $user->id, 'total_votes' => 5]);

        $this->getJson('/api/v1/users/profileowner')
            ->assertOk()
            ->assertJsonPath('data.user.username', 'profileowner')
            ->assertJsonPath('data.stats.posts_count', 2)
            ->assertJsonPath('data.stats.total_votes_received', 10);
    }

    public function test_check_username_availability(): void
    {
        User::factory()->create(['username' => 'takenname']);

        $this->getJson('/api/v1/users/check-username?username=takenname')
            ->assertOk()->assertJsonPath('data.available', false);

        $this->getJson('/api/v1/users/check-username?username=freshname')
            ->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_update_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me', [
            'first_name' => 'Updated',
            'ai_insight_preference' => 'hidden',
        ])->assertOk()->assertJsonPath('data.first_name', 'Updated');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Updated', 'ai_insight_preference' => 'hidden']);
    }

    public function test_me_returns_private_fields_for_self(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['data' => ['linked_providers', 'has_password']]);
    }
}
