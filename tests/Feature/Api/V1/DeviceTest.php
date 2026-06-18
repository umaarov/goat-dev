<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/devices', ['token' => 'abc'])->assertUnauthorized();
    }

    public function test_register_list_and_delete_device(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/devices', ['token' => 'device-token-1', 'platform' => 'ios'])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'device-token-1',
            'platform' => 'ios',
        ]);

        $this->getJson('/api/v1/me/devices')->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/me/devices', ['token' => 'device-token-1'])->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'device-token-1']);
    }

    public function test_registering_existing_token_reassigns_to_caller(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        DeviceToken::create(['user_id' => $first->id, 'token' => 'shared-token', 'platform' => 'android']);

        Sanctum::actingAs($second);
        $this->postJson('/api/v1/me/devices', ['token' => 'shared-token', 'platform' => 'android'])
            ->assertCreated();

        $this->assertDatabaseHas('device_tokens', ['token' => 'shared-token', 'user_id' => $second->id]);
        $this->assertSame(1, DeviceToken::where('token', 'shared-token')->count());
    }

    public function test_register_validates_platform(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/devices', ['token' => 'tok', 'platform' => 'windows-phone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }
}
