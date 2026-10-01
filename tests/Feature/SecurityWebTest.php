<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\QueuedResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');
        $csp = $response->headers->get('Content-Security-Policy');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // enforced: only nonced scripts, no inline handlers, no plugins, no base/form hijack
        $this->assertMatchesRegularExpression("/script-src 'nonce-[A-Za-z0-9+\\/=]+' 'strict-dynamic'/", $csp);
        $this->assertStringContainsString("script-src-attr 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);

        // monitored: broad fetch directives
        $this->assertStringContainsString("img-src 'self'", $response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_every_script_tag_in_a_rendered_page_carries_the_csp_nonce(): void
    {
        $response = $this->get('/login');
        $nonce = preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;
        $this->assertNotNull($nonce);

        preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $tags);
        foreach ($tags[0] as $tag) {
            if (str_contains($tag, 'application/ld+json')) {
                continue;
            }
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag, "script tag without nonce: {$tag}");
        }
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|input|submit|load|error)=/i', $response->getContent());
    }

    public function test_cached_pages_always_carry_the_nonce_of_the_current_response(): void
    {
        config(['responsecache.enabled' => true]);
        \Spatie\ResponseCache\Facades\ResponseCache::clear();

        foreach ([1, 2, 3] as $i) {
            $response = $this->get('/about');
            preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);

            $this->assertStringContainsString('nonce="'.$m[1].'"', $response->getContent(), "request {$i}: body nonce != header nonce");
            $this->assertStringNotContainsString(\App\Http\Middleware\SecurityHeaders::placeholder(), $response->getContent());
        }
    }

    public function test_csp_nonce_differs_per_request(): void
    {
        $a = $this->get('/login')->headers->get('Content-Security-Policy');
        $b = $this->get('/login')->headers->get('Content-Security-Policy');

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

    public function test_web_forgot_password_does_not_reveal_registered_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ghost@example.com']);

        $known->assertSessionHas('success')->assertSessionHasNoErrors();
        $unknown->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame(session('success'), $known->getSession()->get('success'));
        Notification::assertSentTo($user, QueuedResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_web_password_reset_revokes_old_sessions_and_sets_the_new_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-1')]);
        $request = \Illuminate\Http\Request::create('/');
        app(AuthTokenService::class)->issueToken($user, $request);
        $stolen = RefreshToken::where('user_id', $user->id)->first();

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($stolen->fresh()->revoked_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-passphrase', $user->fresh()->password));
    }

    public function test_deactivation_revokes_credentials_and_the_old_cookie_cannot_crash_or_log_in(): void
    {
        $user = User::factory()->create();
        $cookie = app(AuthTokenService::class)->issueToken($user, \Illuminate\Http\Request::create('/'));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/profile/deactivate')
            ->assertRedirect('/');

        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());

        $this->flushSession();
        $response = $this->withUnencryptedCookie(AuthTokenService::cookieName(), $cookie->getValue())->get('/about');

        $response->assertOk();
        $response->assertCookieExpired(AuthTokenService::cookieName());
        $this->assertGuest();
    }

    public function test_verification_token_is_compared_safely(): void
    {
        $service = new \App\Services\EmailVerificationService();
        $user = User::factory()->unverified()->create(['email_verification_token' => 'right-token']);

        $this->assertFalse($service->verify($user, 'wrong-token'));
        $this->assertFalse($service->verify($user, ''));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertTrue($service->verify($user, 'right-token'));
    }

    public function test_sessions_issued_before_a_revocation_are_logged_out_but_the_current_one_survives(): void
    {
        $user = User::factory()->create();

        $old = time() - 100;
        $user->forceFill(['sessions_invalid_before' => time() - 10])->saveQuietly();

        // issued before the cutoff: dead
        $this->actingAs($user)->withSession([\App\Http\Middleware\EnforceSessionRevocation::KEY => $old])
            ->get('/notifications')->assertRedirect(route('login'));
        $this->assertGuest();

        // unstamped (pre-feature) session with a cutoff: also dead
        $this->flushSession();
        $this->actingAs($user)->get('/notifications')->assertRedirect(route('login'));

        // issued after the cutoff: fine
        $this->flushSession();
        $this->actingAs($user)->withSession([\App\Http\Middleware\EnforceSessionRevocation::KEY => time()])
            ->get('/notifications')->assertOk();
    }

    public function test_logging_in_stamps_the_session_and_unrevoked_users_are_not_disturbed(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-1'), 'email' => 'stamp@example.com']);

        $this->post('/login', ['login_identifier' => 'stamp@example.com', 'password' => 'correct-horse-1']);

        $this->assertAuthenticated();
        $this->assertNotNull(session(\App\Http\Middleware\EnforceSessionRevocation::KEY));
        $this->get('/about')->assertOk();
        $this->assertAuthenticated();
    }

    public function test_log_out_other_devices_really_ends_other_sessions_with_the_redis_style_driver(): void
    {
        config(['session.driver' => 'array']);
        $user = User::factory()->create();
        $request = \Illuminate\Http\Request::create('/');
        app(AuthTokenService::class)->issueToken($user, $request);
        $otherDevice = time() - 500;

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time(), \App\Http\Middleware\EnforceSessionRevocation::KEY => time() - 5])
            ->post('/profile/sessions/terminate-all');

        $response->assertRedirect(route('profile.edit'));
        $response->assertCookie(AuthTokenService::cookieName());
        $this->assertSame(1, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count(), 'only this device keeps a refresh token');

        // the other device's old session is rejected
        $this->flushSession();
        $this->actingAs($user->fresh())->withSession([\App\Http\Middleware\EnforceSessionRevocation::KEY => $otherDevice])
            ->get('/notifications')->assertRedirect(route('login'));
    }

    public function test_hostile_post_text_cannot_break_out_of_inline_script_or_json_ld(): void
    {
        $evil = '</script><script>window.__x=1</script><!--<script>';
        $post = \App\Models\Post::factory()->create(['question' => $evil, 'option_one_title' => 'a', 'option_two_title' => 'b']);

        $html = $this->get("/@{$post->user->username}/post/{$post->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>window.__x', $html);
        $this->assertStringNotContainsString('<!--<script>', $html);
        $this->assertStringContainsString('\u003C\/script\u003E', $html, 'the title must be present, hex-escaped, inside the JSON-LD');
    }

    public function test_personal_data_export_needs_a_recent_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-1')]);

        $this->actingAs($user)->post('/profile/export')->assertRedirect(route('password.confirm'));

        $this->flushSession();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/profile/export')->assertOk();
    }

    public function test_confirming_the_password_never_lands_on_a_post_only_url(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse-1')]);

        // no stored intended url: the fallback must be a GET-able page
        $this->actingAs($user)->post('/confirm-password', ['password' => 'correct-horse-1'])
            ->assertRedirect(route('profile.edit'));
    }

    public function test_social_only_users_are_sent_to_set_a_password_before_exporting(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-123']);

        $this->actingAs($user)->post('/profile/export')->assertRedirect(route('password.set.form'));
    }

    public function test_ai_picture_generation_is_tightly_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2 per minute: the third attempt is refused before any paid API is reached
        $codes = collect(range(1, 3))->map(fn () => $this->post('/profile/generate-picture', ['prompt' => 'a goat'])->getStatusCode());

        $this->assertSame(429, $codes->last(), 'statuses: '.$codes->implode(','));
        $this->assertNotContains(429, $codes->take(2)->all());
    }

    public function test_edits_that_trigger_paid_moderation_are_rate_limited_per_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $codes = collect(range(1, 14))->map(fn () => $this->put('/profile/update', [])->getStatusCode());

        $this->assertContains(429, $codes->all(), 'no throttle after 14 edits: '.$codes->implode(','));
        $this->assertSame(12, $codes->reject(fn ($c) => $c === 429)->count());
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
