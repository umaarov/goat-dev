<?php

namespace Tests\Feature\Api\V1;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_requires_verification(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Jane',
            'username' => 'jane_doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verification_required', true);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'email_verified_at' => null]);
    }

    public function test_login_returns_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login_identifier' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user']]);
    }

    public function test_login_rejects_unverified_user(): void
    {
        $user = User::factory()->unverified()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'login_identifier' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(403)->assertJsonPath('error_code', 'email_not_verified');
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'login_identifier' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(401)->assertJsonPath('error_code', 'invalid_credentials');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()
            ->assertJsonPath('error_code', 'authentication_required');
    }

    public function test_refresh_rotates_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $login = $this->postJson('/api/v1/auth/login', [
            'login_identifier' => $user->email,
            'password' => 'secret123',
        ])->json('data');

        $refreshed = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ]);

        $refreshed->assertOk();
        $this->assertNotSame($login['refresh_token'], $refreshed->json('data.refresh_token'));
        $this->assertNotSame($login['access_token'], $refreshed->json('data.access_token'));
    }

    public function test_invalid_refresh_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'nonsense'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_refresh_token');
    }

    public function test_reused_refresh_token_outside_grace_revokes_all_sessions(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $login = $this->postJson('/api/v1/auth/login', [
            'login_identifier' => $user->email,
            'password' => 'secret123',
        ])->json('data');

        // First refresh rotates the token (revokes the old one with a grace window).
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['refresh_token']])->assertOk();

        // Force the grace window to have elapsed, then reuse the old token => theft.
        RefreshToken::query()->whereNotNull('revoked_at')->update(['grace_period_ends_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['refresh_token']])
            ->assertStatus(401);

        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }
}
