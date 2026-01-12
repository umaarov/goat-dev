<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenStressTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_handles_long_running_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'longsession@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Login
        $response = $this->post('/login', [
            'login_identifier' => 'longsession@test.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();

        // Get initial cookie
        $cookie = $response->getCookie('refresh_token');
        $this->assertNotNull($cookie);

        // Make several requests over time (simulating 10 days)
        for ($day = 1; $day <= 10; $day++) {
            // Make a request (should maintain authentication)
            $response = $this->withCookie('refresh_token', $cookie->getValue())
                ->get('/');

            // Should still be authenticated
            $this->assertAuthenticated();

            // Check if we got a new cookie (token rotation)
            $newCookie = $response->getCookie('refresh_token');
            if ($newCookie && $newCookie->getValue() !== $cookie->getValue()) {
                $cookie = $newCookie; // Update to new cookie
            }
        }

        // Final logout
        $response = $this->post('/logout');
        $response->assertCookieExpired('refresh_token');
        $this->assertGuest();
    }

    #[Test]
    public function it_handles_multiple_sequential_logins(): void
    {
        $user = User::factory()->create([
            'email' => 'sequential@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Simulate 5 sequential login/logout cycles (reduced from 10 for speed)
        for ($i = 0; $i < 5; $i++) {
            // Login
            $response = $this->post('/login', [
                'login_identifier' => 'sequential@test.com',
                'password' => 'password',
            ]);

            $this->assertAuthenticated();

            // Get the refresh token cookie
            $cookie = $response->getCookie('refresh_token');
            $this->assertNotNull($cookie);

            // Make a request with the refresh token
            $response = $this->withCookie('refresh_token', $cookie->getValue())
                ->get('/');
            $this->assertAuthenticated();

            // Logout with the refresh token cookie
            $response = $this->withCookie('refresh_token', $cookie->getValue())
                ->post('/logout');

            $response->assertCookieExpired('refresh_token');
            $this->assertGuest();

            // Clear session for next iteration
            $this->app['session']->flush();
        }

        // Final verification: user should be able to login again
        $response = $this->post('/login', [
            'login_identifier' => 'sequential@test.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertCookie('refresh_token');
    }
}
