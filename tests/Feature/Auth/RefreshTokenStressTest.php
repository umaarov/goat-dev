<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

        $response = $this->post('/login', [
            'login_identifier' => 'longsession@test.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $cookie = $response->getCookie('refresh_token');
        $this->assertNotNull($cookie);
        for ($day = 1; $day <= 10; $day++) {
            $response = $this->withCookie('refresh_token', $cookie->getValue())
                ->get('/');

            $this->assertAuthenticated();
            $newCookie = $response->getCookie('refresh_token');
            if ($newCookie && $newCookie->getValue() !== $cookie->getValue()) {
                $cookie = $newCookie;
            }
        }

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

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'login_identifier' => 'sequential@test.com',
                'password' => 'password',
            ]);

            $this->assertAuthenticated();
            $cookie = $response->getCookie('refresh_token');
            $this->assertNotNull($cookie);
            $response = $this->withCookie('refresh_token', $cookie->getValue())
                ->get('/');
            $this->assertAuthenticated();
            $response = $this->withCookie('refresh_token', $cookie->getValue())
                ->post('/logout');

            $response->assertCookieExpired('refresh_token');
            $this->assertGuest();
            $this->app['session']->flush();
        }

        $response = $this->post('/login', [
            'login_identifier' => 'sequential@test.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertCookie('refresh_token');
    }
}
