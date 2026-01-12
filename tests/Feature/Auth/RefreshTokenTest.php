<?php


namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private AuthTokenService $authTokenService;
    private User $user;
    private string $password = 'password123';

    #[Test]
    public function it_issues_refresh_token_on_successful_login(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');

        $cookie = $this->authTokenService->issueToken($this->user, $request);

        $this->assertNotNull($cookie);
        $this->assertNotEmpty($cookie->getValue());

        $hashedToken = hash('sha256', $cookie->getValue());
        $this->assertDatabaseHas('refresh_tokens', [
            'token' => $hashedToken,
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_validates_correct_refresh_token(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');

        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $tokenValue = $cookie->getValue();

        $tokenModel = $this->authTokenService->getValidToken($tokenValue);

        $this->assertNotNull($tokenModel);
        $this->assertEquals($this->user->id, $tokenModel->user_id);
        $this->assertNull($tokenModel->revoked_at);
    }

    #[Test]
    public function it_rejects_expired_refresh_token(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');
        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $hashedToken = hash('sha256', $cookie->getValue());
        $token = RefreshToken::where('token', $hashedToken)->first();
        $token->update(['expires_at' => now()->subDay()]);
        $tokenModel = $this->authTokenService->getValidToken($cookie->getValue());
        $this->assertNull($tokenModel);
    }

    #[Test]
    public function it_revokes_all_tokens_on_logout(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');
        $this->authTokenService->issueToken($this->user, $request);
        $this->authTokenService->issueToken($this->user, $request);

        $activeTokens = RefreshToken::where('user_id', $this->user->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertGreaterThan(0, $activeTokens);
        $this->authTokenService->revokeAllTokensForUser($this->user);
        $activeTokensAfter = RefreshToken::where('user_id', $this->user->id)
            ->whereNull('revoked_at')
            ->count();

        $this->assertEquals(0, $activeTokensAfter);
    }

    #[Test]
    public function it_handles_malformed_tokens(): void
    {
        $tokenModel = $this->authTokenService->getValidToken('malformed_token');
        $this->assertNull($tokenModel);

        $tokenModel = $this->authTokenService->getValidToken('');
        $this->assertNull($tokenModel);
        // $tokenModel = $this->authTokenService->getValidToken(null);
        // $this->assertNull($tokenModel);
    }

    #[Test]
    public function it_properly_creates_and_clears_cookies(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');
        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $this->assertEquals('refresh_token', $cookie->getName());
        $this->assertNotEmpty($cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $clearCookie = $this->authTokenService->clearCookie();
        $this->assertEquals('refresh_token', $clearCookie->getName());
        $this->assertLessThan(time(), $clearCookie->getExpiresTime());
    }

    #[Test]
    public function it_revokes_specific_token(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');

        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $tokenValue = $cookie->getValue();
        $tokenModel = $this->authTokenService->getValidToken($tokenValue);
        $this->assertNotNull($tokenModel);
        $this->authTokenService->revokeToken($tokenModel);

        $tokenModelAfter = $this->authTokenService->getValidToken($tokenValue);
        $this->assertNull($tokenModelAfter);
    }

    #[Test]
    public function it_prevents_token_reuse(): void
    {
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'TestAgent');

        $cookie = $this->authTokenService->issueToken($this->user, $request);
        $tokenValue = $cookie->getValue();
        $tokenModel = $this->authTokenService->getValidToken($tokenValue);
        $this->assertNotNull($tokenModel);

        $this->authTokenService->revokeToken($tokenModel);
        $tokenModelAgain = $this->authTokenService->getValidToken($tokenValue);
        $this->assertNull($tokenModelAgain);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authTokenService = app(AuthTokenService::class);

        $this->user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt($this->password),
            'email_verified_at' => now(),
        ]);
    }
}
