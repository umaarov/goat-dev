<?php


namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginWithRefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'password123';

    #[Test]
    public function user_can_login_with_credentials_and_get_refresh_token(): void
    {
        $response = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $response->assertRedirect();
        $response->assertCookie('refresh_token');
        $this->assertAuthenticated();
    }

    #[Test]
    public function user_can_logout_and_clear_refresh_token(): void
    {
        $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $this->assertAuthenticated();
        $response = $this->post('/logout');
        $response->assertRedirect();
        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }

    #[Test]
    public function unauthenticated_user_with_valid_refresh_token_gets_authenticated(): void
    {
        $loginResponse = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $cookie = $loginResponse->getCookie('refresh_token');
        $tokenValue = $cookie->getValue();
        Auth::logout();
        $this->app['session']->flush();
        $this->assertGuest();
        $response = $this->withCookie('refresh_token', $tokenValue)
            ->get('/');

        $newCookie = $response->getCookie('refresh_token');
        $this->assertNotNull($newCookie);
        $this->assertNotEquals($tokenValue, $newCookie->getValue());
    }

    #[Test]
    public function invalid_refresh_token_gets_cleared(): void
    {
        $response = $this->withCookie('refresh_token', 'invalid_token_value')
            ->get('/');

        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }

    #[Test]
    public function expired_refresh_token_gets_cleared(): void
    {
        $loginResponse = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $cookie = $loginResponse->getCookie('refresh_token');
        $tokenValue = $cookie->getValue();

        $hashedToken = hash('sha256', $tokenValue);
        RefreshToken::where('token', $hashedToken)
            ->update(['expires_at' => now()->subDay()]);

        Auth::logout();
        $response = $this->withCookie('refresh_token', $tokenValue)
            ->get('/');

        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt($this->password),
            'email_verified_at' => now(),
        ]);
    }
}
