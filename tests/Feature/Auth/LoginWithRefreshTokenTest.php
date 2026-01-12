<?php


namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginWithRefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt($this->password),
            'email_verified_at' => now(),
        ]);
    }

    #[Test]
    public function user_can_login_with_credentials_and_get_refresh_token(): void
    {
        $response = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $response->assertRedirect();
        $response->assertCookie('refresh_token');

        // User should be authenticated
        $this->assertAuthenticated();
    }

    #[Test]
    public function user_can_logout_and_clear_refresh_token(): void
    {
        // Login first
        $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $this->assertAuthenticated();

        // Logout
        $response = $this->post('/logout');

        $response->assertRedirect();
        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }

    #[Test]
    public function unauthenticated_user_with_valid_refresh_token_gets_authenticated(): void
    {
        // First login to get a token
        $loginResponse = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $cookie = $loginResponse->getCookie('refresh_token');
        $tokenValue = $cookie->getValue();

        // Clear session (simulate session expiry)
        Auth::logout();
        $this->app['session']->flush();

        $this->assertGuest();

        // Make request with refresh token
        $response = $this->withCookie('refresh_token', $tokenValue)
            ->get('/'); // Home page or any protected route

        // Should be redirected (Laravel handles this) and get new cookie
        $newCookie = $response->getCookie('refresh_token');
        $this->assertNotNull($newCookie);
        $this->assertNotEquals($tokenValue, $newCookie->getValue());
    }

    #[Test]
    public function invalid_refresh_token_gets_cleared(): void
    {
        // Make request with invalid token
        $response = $this->withCookie('refresh_token', 'invalid_token_value')
            ->get('/');

        // Cookie should be cleared
        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }

    #[Test]
    public function expired_refresh_token_gets_cleared(): void
    {
        // Login to get token
        $loginResponse = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => $this->password,
        ]);

        $cookie = $loginResponse->getCookie('refresh_token');
        $tokenValue = $cookie->getValue();

        // Manually expire the token in database
        $hashedToken = hash('sha256', $tokenValue);
        \App\Models\RefreshToken::where('token', $hashedToken)
            ->update(['expires_at' => now()->subDay()]);

        // Clear session
        Auth::logout();

        // Try to use expired token
        $response = $this->withCookie('refresh_token', $tokenValue)
            ->get('/');

        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }
}
