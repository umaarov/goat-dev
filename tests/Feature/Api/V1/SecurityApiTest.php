<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\SocialAccountService;
use App\Services\SocialUserData;
use App\Services\TelegramAuthService;
use App\Support\ImageGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use App\Notifications\QueuedResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password = 'correct-horse-1'): array
    {
        return $this->postJson('/api/v1/auth/login', [
            'login_identifier' => $user->email,
            'password' => $password,
        ])->assertOk()->json('data');
    }

    private function user(): User
    {
        return User::factory()->create(['password' => bcrypt('correct-horse-1')]);
    }

    public function test_reused_refresh_token_revokes_the_whole_family_when_grace_is_zero(): void
    {
        config(['security.refresh.grace_seconds' => 0]);
        $tokens = $this->login($this->user());

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertOk();

        // the stolen/old token is replayed
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertStatus(401);
        $this->assertDatabaseMissing('refresh_tokens', ['revoked_at' => null]);
    }

    public function test_refresh_token_is_stored_hashed_and_access_token_is_prefixed(): void
    {
        $tokens = $this->login($this->user());

        $this->assertDatabaseMissing('refresh_tokens', ['token' => $tokens['refresh_token']]);
        $this->assertDatabaseHas('refresh_tokens', ['token' => hash('sha256', $tokens['refresh_token'])]);
        $this->assertStringContainsString('goat_', $tokens['access_token']);
    }

    public function test_access_token_is_short_lived(): void
    {
        $tokens = $this->login($this->user());

        $this->assertLessThanOrEqual(30 * 60, $tokens['expires_in']);
    }

    public function test_password_change_revokes_old_credentials_and_returns_a_fresh_pair(): void
    {
        $user = $this->user();
        $old = $this->login($user);

        $response = $this->withToken($old['access_token'])->postJson('/api/v1/me/change-password', [
            'current_password' => 'correct-horse-1',
            'new_password' => 'a-brand-new-passphrase',
            'new_password_confirmation' => 'a-brand-new-passphrase',
        ])->assertOk();

        $fresh = $response->json('data');
        $this->assertNotSame($old['refresh_token'], $fresh['refresh_token']);

        // the old refresh token is dead
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $old['refresh_token']])->assertStatus(401);
        // the new one works
        $this->withToken($fresh['access_token'])->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_weak_passwords_are_rejected_at_registration(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Jane',
            'username' => 'jane_doe',
            'email' => 'jane@example.com',
            'password' => 'short123',
            'password_confirmation' => 'short123',
            'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_failure_looks_identical_for_unknown_and_known_accounts(): void
    {
        $known = $this->user();

        $a = $this->postJson('/api/v1/auth/login', ['login_identifier' => $known->email, 'password' => 'nope-nope-nope']);
        $b = $this->postJson('/api/v1/auth/login', ['login_identifier' => 'ghost@example.com', 'password' => 'nope-nope-nope']);

        $this->assertSame($a->status(), $b->status());
        $this->assertSame($a->json('error_code'), $b->json('error_code'));
        $this->assertSame($a->json('message'), $b->json('message'));
    }

    public function test_login_is_rate_limited_per_account(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login_identifier' => $user->email, 'password' => 'wrong-wrong-1']);
        }

        $this->postJson('/api/v1/auth/login', ['login_identifier' => $user->email, 'password' => 'correct-horse-1'])
            ->assertStatus(429);
    }

    public function test_password_reset_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/password/forgot', ['email' => 'a@example.com']);
        }

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'a@example.com'])->assertStatus(429);
    }

    public function test_password_reset_journey_is_uniform_and_revokes_everything(): void
    {
        Notification::fake();
        $user = $this->user();
        $old = $this->login($user);

        $known = $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ghost@example.com']);

        // an attacker cannot tell the two apart
        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, QueuedResetPassword::class);
        Notification::assertCount(1);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertOk();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $old['refresh_token']])->assertStatus(401);
        $this->withToken($old['access_token'])->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['login_identifier' => $user->email, 'password' => 'correct-horse-1'])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['login_identifier' => $user->email, 'password' => 'a-brand-new-passphrase'])->assertOk();
    }

    public function test_timing_guard_makes_the_dummy_hash_once_not_on_every_request(): void
    {
        Cache::flush();
        $key = \App\Support\TimingGuard::cacheKey();
        $this->assertFalse(Cache::has($key));

        \App\Support\TimingGuard::burn('a');
        $first = Cache::get($key);
        $this->assertNotEmpty($first);

        \App\Support\TimingGuard::burn('b');
        $this->assertSame($first, Cache::get($key), 'the dummy hash must be reused, not regenerated');
    }

    public function test_forgot_password_takes_the_same_minimum_time_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $user = $this->user();

        foreach ([$user->email, 'ghost@example.com'] as $email) {
            $started = microtime(true);
            $this->postJson('/api/v1/auth/password/forgot', ['email' => $email])->assertOk();

            $this->assertGreaterThanOrEqual(0.45, microtime(true) - $started, "{$email} answered too fast");
        }
    }

    public function test_reset_token_table_exists_and_a_token_is_single_use(): void
    {
        $user = $this->user();
        $token = Password::createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'another-long-passphrase', 'password_confirmation' => 'another-long-passphrase'];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertStatus(422);
    }

    public function test_server_errors_do_not_leak_internals(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/_boom', fn () => throw new \RuntimeException('SQLSTATE[HY000] secret path /var/www'));

        $this->getJson('/api/_boom')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Server error.')
            ->assertJsonMissing(['message' => 'SQLSTATE[HY000] secret path /var/www']);
    }

    public function test_unverified_social_email_never_links_an_existing_account(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'github_id' => null]);

        $result = app(SocialAccountService::class)->resolve('github', new SocialUserData(
            id: '999',
            email: 'victim@example.com',
            name: 'Attacker',
            nickname: 'attacker',
            emailVerified: false,
        ));

        $this->assertTrue($result['created']);
        $this->assertNotSame($victim->id, $result['user']->id);
        $this->assertNull($victim->fresh()->github_id);
        $this->assertNotSame('victim@example.com', $result['user']->email);
    }

    public function test_verified_social_email_links_the_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com', 'github_id' => null]);

        $result = app(SocialAccountService::class)->resolve('github', new SocialUserData(
            id: '1000',
            email: 'me@example.com',
            name: 'Me',
            nickname: 'me',
            emailVerified: true,
        ));

        $this->assertFalse($result['created']);
        $this->assertSame($user->id, $result['user']->id);
    }

    public function test_github_login_is_refused_when_the_oauth_app_is_not_configured(): void
    {
        config(['services.github.client_id' => 'your_github_client_id', 'services.github.client_secret' => 'x']);

        $this->postJson('/api/v1/auth/social/github', ['token' => 'gho_token_from_some_other_app'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'social_verification_failed');
    }

    public function test_x_login_with_a_bare_access_token_is_refused(): void
    {
        config(['services.x.client_id' => 'id', 'services.x.client_secret' => 'secret']);

        $this->postJson('/api/v1/auth/social/x', ['token' => 'bare-access-token'])
            ->assertStatus(401);
    }

    public function test_telegram_payload_is_single_use_and_expires(): void
    {
        config(['services.telegram.bot_token' => '123456:TEST-TOKEN']);
        $service = new TelegramAuthService();

        $payload = ['id' => 42, 'first_name' => 'T', 'auth_date' => time()];
        ksort($payload);
        $check = collect($payload)->map(fn ($v, $k) => "$k=$v")->implode("\n");
        $payload['hash'] = hash_hmac('sha256', $check, hash('sha256', '123456:TEST-TOKEN', true));

        Cache::flush();
        $this->assertNotNull($service->validate($payload));
        $this->assertNull($service->validate($payload), 'replay must be rejected');

        $old = ['id' => 42, 'first_name' => 'T', 'auth_date' => time() - 4000];
        ksort($old);
        $old['hash'] = hash_hmac('sha256', collect($old)->map(fn ($v, $k) => "$k=$v")->implode("\n"), hash('sha256', '123456:TEST-TOKEN', true));
        $this->assertNull($service->validate($old), 'stale payload must be rejected');
    }

    public function test_telegram_forged_hash_is_rejected(): void
    {
        config(['services.telegram.bot_token' => '123456:TEST-TOKEN']);

        $this->assertNull((new TelegramAuthService())->validate([
            'id' => 1, 'first_name' => 'X', 'auth_date' => time(), 'hash' => str_repeat('a', 64),
        ]));
    }

    public function test_image_guard_rejects_decompression_bombs_and_non_images(): void
    {
        $dir = sys_get_temp_dir();

        $text = tempnam($dir, 'txt');
        file_put_contents($text, '<?php echo 1;');
        $this->expectValidation(fn () => ImageGuard::assertSafe($text));

        $wide = tempnam($dir, 'png');
        $im = imagecreatetruecolor(13000, 1);
        imagepng($im, $wide);
        $this->expectValidation(fn () => ImageGuard::assertSafe($wide));

        $ok = tempnam($dir, 'png');
        imagepng(imagecreatetruecolor(64, 64), $ok);
        ImageGuard::assertSafe($ok);
        $this->assertTrue(true);
    }

    public function test_hashing_upgrades_old_bcrypt_hashes_on_api_login(): void
    {
        // account created while the app still hashed with bcrypt
        $user = User::factory()->create(['password' => 'correct-horse-1']);
        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));

        // the app is then switched to argon2id
        config(['hashing.driver' => 'argon2id', 'hashing.argon.verify' => false]);
        app('hash')->forgetDrivers();

        $this->login($user);

        $this->assertStringStartsWith('$argon2id$', $user->fresh()->getRawOriginal('password'));
        $this->assertTrue(Hash::check('correct-horse-1', $user->fresh()->password));
    }

    private function expectValidation(callable $fn): void
    {
        try {
            $fn();
            $this->fail('ValidationException expected');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
