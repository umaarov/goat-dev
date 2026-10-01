<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertMatchesRegularExpression("/script-src 'nonce-[A-Za-z0-9+\\/=]+' 'strict-dynamic'/", $response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_csp_nonce_differs_per_request(): void
    {
        $a = $this->get('/login')->headers->get('Content-Security-Policy-Report-Only');
        $b = $this->get('/login')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotSame($a, $b);
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_csp_report_endpoint_accepts_reports_without_csrf(): void
    {
        $this->postJson('/csp-report', ['csp-report' => ['blocked-uri' => 'inline', 'effective-directive' => 'script-src']])
            ->assertNoContent();
    }

    public function test_sonar_webhook_requires_a_valid_signature(): void
    {
        Http::fake();
        $body = json_encode(['qualityGate' => ['status' => 'ERROR'], 'branch' => ['name' => 'main']]);

        $this->call('POST', '/webhooks/sonar', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);

        $this->call('POST', '/webhooks/sonar', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SONAR_WEBHOOK_HMAC_SHA256' => hash_hmac('sha256', $body, 'wrong-secret'),
        ], $body)->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_sonar_webhook_with_valid_signature_passes_when_gate_ok(): void
    {
        $body = json_encode(['qualityGate' => ['status' => 'OK']]);

        $this->call('POST', '/webhooks/sonar', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SONAR_WEBHOOK_HMAC_SHA256' => hash_hmac('sha256', $body, 'test-sonar-secret'),
        ], $body)->assertOk();
    }

    public function test_sonar_webhook_creates_one_issue_per_branch(): void
    {
        config(['services.github.api_token' => 'ghp_test']);
        Cache::flush();
        Http::fake(['api.github.com/*' => Http::response(['html_url' => 'https://github.com/x/y/issues/1'], 201)]);
        $body = json_encode(['qualityGate' => ['status' => 'ERROR'], 'branch' => ['name' => 'main', 'url' => 'https://evil.example/x'], 'project' => ['key' => '<script>k</script>']]);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SONAR_WEBHOOK_HMAC_SHA256' => hash_hmac('sha256', $body, 'test-sonar-secret'),
        ];

        $this->call('POST', '/webhooks/sonar', [], [], [], $server, $body)->assertStatus(201);
        $this->call('POST', '/webhooks/sonar', [], [], [], $server, $body)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $text = $request['body'];

            return str_contains($text, 'https://sonarcloud.io')   // hostile url replaced
                && !str_contains($text, 'evil.example')
                && !str_contains($text, '<script>');
        });
    }

    public function test_language_switch_never_redirects_off_site(): void
    {
        $this->withHeader('Referer', 'https://evil.example/phish')
            ->get('/language/en')
            ->assertRedirect(route('home'));
    }

    public function test_refresh_cookie_is_lax_httponly_and_host_prefixed_when_secure(): void
    {
        config(['session.secure' => true, 'session.domain' => null]);
        $cookie = app(AuthTokenService::class)->createCookie('abc');

        $this->assertSame('__Host-refresh_token', $cookie->getName());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower($cookie->getSameSite()));
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());

        config(['session.secure' => false]);
        $this->assertSame('refresh_token', AuthTokenService::cookieName());
    }

    public function test_concurrent_rotation_has_a_single_winner(): void
    {
        $user = User::factory()->create();
        $service = app(AuthTokenService::class);
        $request = \Illuminate\Http\Request::create('/');
        $service->issueToken($user, $request);
        $token = RefreshToken::first();

        $first = $service->rotateToken($token->fresh(), $request);
        $second = $service->rotateToken($token->fresh(), $request);

        $this->assertNotNull($first);
        $this->assertNull($second, 'a token can only be rotated once');
    }

    public function test_web_password_change_revokes_other_refresh_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-1')]);
        $request = \Illuminate\Http\Request::create('/');
        $request->server->set('REMOTE_ADDR', '10.0.0.9');
        app(AuthTokenService::class)->issueToken($user, $request);
        $stolen = RefreshToken::where('user_id', $user->id)->first();

        $response = $this->actingAs($user)->post('/profile/change-password', [
            'current_password' => 'correct-horse-1',
            'new_password' => 'a-brand-new-passphrase',
            'new_password_confirmation' => 'a-brand-new-passphrase',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull($stolen->fresh()->revoked_at);
        $response->assertCookie(AuthTokenService::cookieName());
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'x@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'x@example.com'])->assertStatus(429);
    }

    public function test_local_disk_is_not_exposed_over_http(): void
    {
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
        $this->assertContains($this->put('/storage/anything.txt', ['x' => 1])->getStatusCode(), [404, 405]);
    }

    public function test_security_check_command_runs(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->artisan('security:check')->assertSuccessful();
    }
}
