<?php


namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DebugRefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_refresh_token_flow(): void
    {
        // Create user directly to avoid factory issues
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        // Test 1: Login
        $response = $this->post('/login', [
            'login_identifier' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $response->assertCookie('refresh_token');

        $cookie = $response->getCookie('refresh_token');
        $this->assertNotNull($cookie);
        $this->assertNotEmpty($cookie->getValue());

        echo "1. Login successful. Refresh token issued.\n";
        echo "Cookie name: {$cookie->getName()}\n";
        echo "Cookie value length: " . strlen($cookie->getValue()) . "\n";
        echo "Is HttpOnly: " . ($cookie->isHttpOnly() ? 'Yes' : 'No') . "\n";
        echo "Is Secure: " . ($cookie->isSecure() ? 'Yes' : 'No') . "\n";
        echo "SameSite: " . $cookie->getSameSite() . "\n";

        // Test 2: Access protected route (should work)
        $response = $this->get('/');
        $this->assertAuthenticated();
        echo "2. User is authenticated.\n";

        // Test 3: Clear session but keep cookie (simulate session expiry)
        Auth::logout();
        $this->app['session']->flush();

        $this->assertGuest();
        echo "3. Session cleared. User is guest.\n";

        // Test 4: Try to access with refresh token
        $response = $this->withCookie('refresh_token', $cookie->getValue())
            ->get('/');

        echo "4. Made request with refresh token.\n";
        echo "Status: {$response->getStatusCode()}\n";

        $newCookie = $response->getCookie('refresh_token');
        if ($newCookie) {
            echo "New cookie issued: Yes\n";
            echo "New cookie same as old: " . ($newCookie->getValue() === $cookie->getValue() ? 'Yes' : 'No') . "\n";
        } else {
            echo "New cookie issued: No\n";
        }

        // Test 5: Logout
        $response = $this->post('/logout');
        $response->assertCookieExpired('refresh_token');
        echo "5. Logout successful. Cookie cleared.\n";
    }

    #[Test]
    public function test_cookie_properties(): void
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User2',
            'username' => 'testuser2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        // Login to check cookie properties
        $response = $this->post('/login', [
            'login_identifier' => 'test2@example.com',
            'password' => 'password123',
        ]);

        $cookie = $response->getCookie('refresh_token');

        // Check cookie settings match your config
        $this->assertEquals(config('session.secure'), $cookie->isSecure());
        $this->assertEquals(config('session.same_site'), $cookie->getSameSite());
        $this->assertTrue($cookie->isHttpOnly());

        echo "Cookie configuration check:\n";
        echo "Config secure: " . (config('session.secure') ? 'true' : 'false') . "\n";
        echo "Cookie secure: " . ($cookie->isSecure() ? 'true' : 'false') . "\n";
        echo "Config same_site: " . config('session.same_site') . "\n";
        echo "Cookie same_site: " . $cookie->getSameSite() . "\n";
    }
}
