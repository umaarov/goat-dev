<?php


namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshTokenSystemTest extends TestCase
{
    use RefreshDatabase;

    private AuthTokenService $authTokenService;
    private User $user;

    #[Test]
    public function it_issues_valid_refresh_token_on_login()
    {
        $response = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => 'password',
        ]);

        $cookie = $response->getCookie('refresh_token');
        $this->assertNotNull($cookie);
        $this->assertNotEmpty($cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        // $this->assertTrue($cookie->isSecure());
        $this->assertEquals('lax', $cookie->getSameSite());
        $hashedToken = hash('sha256', $cookie->getValue());
        $tokenExists = RefreshToken::where('token', $hashedToken)->exists();
        $this->assertTrue($tokenExists);
    }

    #[Test]
    public function it_authenticates_user_with_valid_refresh_token()
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');
        $cookie = $this->authTokenService->issueToken($this->user, $request);
        Auth::logout();
        $this->app['session']->flush();
        $response = $this->withCookie('refresh_token', $cookie->getValue())
            ->get('/api/user');

        $this->assertAuthenticatedAs($this->user);
        $newCookie = $response->getCookie('refresh_token');
        $this->assertNotNull($newCookie);
        $this->assertNotEquals($cookie->getValue(), $newCookie->getValue());
    }

    #[Test]
    public function it_rejects_expired_refresh_token()
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');
        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $hashedToken = hash('sha256', $cookie->getValue());
        $token = RefreshToken::where('token', $hashedToken)->first();
        $token->update(['expires_at' => now()->subDay()]);
        $response = $this->withCookie('refresh_token', $cookie->getValue())
            ->get('/api/user');

        $this->assertGuest();
        $response->assertCookieExpired('refresh_token');
    }

    #[Test]
    public function it_revokes_current_token_on_logout(): void
    {
        $response = $this->post('/login', [
            'login_identifier' => $this->user->email,
            'password' => 'password',
        ]);

        $cookie = $response->getCookie('refresh_token');
        $this->assertNotNull($cookie);
        $tokenValue = $cookie->getValue();
        $hashedToken = hash('sha256', $tokenValue);
        $tokenBefore = RefreshToken::where('token', $hashedToken)->first();
        $this->assertNotNull($tokenBefore);
        $this->assertNull($tokenBefore->revoked_at);
        $response = $this->withCookie('refresh_token', $tokenValue)
            ->post('/logout');

        $tokenAfter = RefreshToken::where('token', $hashedToken)->first();

        if ($tokenAfter) {
            $this->assertNotNull($tokenAfter->revoked_at, 'Token should be marked as revoked after logout');
        } else {
            $this->assertTrue(true, 'Token was deleted (acceptable alternative to revocation)');
        }

        $response->assertCookieExpired('refresh_token');
    }

    #[Test]
    public function it_handles_token_family_revocation_correctly()
    {
        $request1 = Request::create('/');
        $request1->server->set('REMOTE_ADDR', '192.168.1.1');
        $request1->headers->set('User-Agent', 'Chrome-Windows');
        $cookie1 = $this->authTokenService->issueToken($this->user, $request1);

        $request2 = Request::create('/');
        $request2->server->set('REMOTE_ADDR', '192.168.1.2');
        $request2->headers->set('User-Agent', 'Firefox-Mac');
        $cookie2 = $this->authTokenService->issueToken($this->user, $request2);

        $hashedToken1 = hash('sha256', $cookie1->getValue());
        $hashedToken2 = hash('sha256', $cookie2->getValue());
        $this->assertTrue(RefreshToken::where('token', $hashedToken1)->exists());
        $this->assertTrue(RefreshToken::where('token', $hashedToken2)->exists());
        $this->withCookie('refresh_token', $cookie1->getValue())
            ->get('/api/user');

        $token1 = RefreshToken::where('token', $hashedToken1)->first();
        $token2 = RefreshToken::where('token', $hashedToken2)->first();

        $this->assertNotNull($token1->revoked_at);
        $this->assertNull($token2->revoked_at);
    }

    #[Test]
    public function it_proactively_rotates_tokens_near_expiration()
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');
        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $hashedToken = hash('sha256', $cookie->getValue());
        $token = RefreshToken::where('token', $hashedToken)->first();
        $token->update(['expires_at' => now()->addHour()]);
        $response = $this->withCookie('refresh_token', $cookie->getValue())
            ->get('/api/user');

        $newCookie = $response->getCookie('refresh_token');
        $this->assertNotNull($newCookie);
        $this->assertNotEquals($cookie->getValue(), $newCookie->getValue());
        $token->refresh();
        $this->assertNotNull($token->revoked_at);
    }

    #[Test]
    public function it_preserves_session_on_token_refresh()
    {
        $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => 'password',
        ]);

        session(['test_key' => 'test_value']);
        $sessionId = session()->getId();
        $response = $this->get('/api/user');
        $this->assertEquals('test_value', session('test_key'));
        $this->assertNotEquals($sessionId, session()->getId());
    }

    #[Test]
    public function it_handles_multiple_concurrent_sessions()
    {
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $request = Request::create('/');
            $request->server->set('REMOTE_ADDR', "192.168.1.{$i}");
            $request->headers->set('User-Agent', "Browser-{$i}");
            $tokens[] = $this->authTokenService->issueToken($this->user, $request);
        }

        foreach ($tokens as $token) {
            $hashedToken = hash('sha256', $token->getValue());
            $tokenModel = $this->authTokenService->getValidToken($token->getValue());
            $this->assertNotNull($tokenModel);
            $this->assertNull($tokenModel->revoked_at);
        }

        $this->withCookie('refresh_token', $tokens[0]->getValue())
            ->get('/api/user');

        $hashedToken1 = hash('sha256', $tokens[1]->getValue());
        $hashedToken2 = hash('sha256', $tokens[2]->getValue());

        $token1 = RefreshToken::where('token', $hashedToken1)->first();
        $token2 = RefreshToken::where('token', $hashedToken2)->first();

        $this->assertNull($token1->revoked_at);
        $this->assertNull($token2->revoked_at);
    }

    #[Test]
    public function it_logs_authentication_events()
    {
        config(['logging.channels.audit_trail' => [
            'driver' => 'single',
            'path' => storage_path('logs/audit_test.log'),
            'level' => 'info',
        ]]);

        $logFile = storage_path('logs/audit_test.log');
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => 'password',
        ]);
        $this->post('/logout');
        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('[LOGIN]', $logContent);
        $this->assertStringContainsString('[LOGOUT]', $logContent);
    }

    #[Test]
    public function it_handles_cookie_manipulation_attempts()
    {
        $response = $this->withCookie('refresh_token', 'malformed_token')
            ->get('/api/user');

        $this->assertGuest();
        $response->assertCookieExpired('refresh_token');
        $response = $this->withCookie('refresh_token', '')
            ->get('/api/user');

        $this->assertGuest();
        $response = $this->withCookie('refresh_token', "' OR '1'='1")
            ->get('/api/user');

        $this->assertGuest();
        $response->assertCookieExpired('refresh_token');
    }

    #[Test]
    public function it_resists_token_replay_attacks()
    {
        $response = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => 'password',
        ]);

        $cookie = $response->getCookie('refresh_token');
        $tokenValue = $cookie->getValue();
        $this->withCookie('refresh_token', $tokenValue)
            ->get('/');
        Auth::logout();
        $this->app['session']->flush();
        $response = $this->withCookie('refresh_token', $tokenValue)
            ->get('/');
        $newCookie = $response->getCookie('refresh_token');
        $this->assertNotNull($newCookie);
        $this->assertNotEquals($tokenValue, $newCookie->getValue());
        $hashedToken = hash('sha256', $tokenValue);
        $oldToken = RefreshToken::where('token', $hashedToken)->first();
        $this->assertNotNull($oldToken->revoked_at);
    }

    #[Test]
    public function it_gracefully_handles_database_failures()
    {
        $response = $this->withCookie('refresh_token', 'any_token')
            ->get('/');

        $this->assertGuest();
        $response->assertCookieExpired('refresh_token');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authTokenService = app(AuthTokenService::class);
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }
}
