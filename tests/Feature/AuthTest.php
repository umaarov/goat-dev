<?php

namespace Tests\Feature;

use App\Events\UserRegistered;
use App\Mail\EmailVerification;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    //setup the test environment
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Mail::fake();
        Storage::fake('public');
    }

    //registration test
    #[Test]
    public function registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee(__('messages.register'));
    }

    #[Test]
    public function a_user_can_register_successfully_without_a_profile_picture()
    {
        $userData = [
            'first_name' => 'User',
            'last_name' => 'User',
            'username' => 'cooluser',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => 'on',
        ];

        $this->mock(AvatarService::class, function ($mock) {
            $mock->shouldReceive('generateInitialsAvatar')->once()->andReturn('profile_pictures/initial_user.png');
        });
        $this->mock(EmailVerificationService::class, function ($mock) {
            $mock->shouldReceive('sendVerificationEmail')->once();
            $mock->shouldReceive('generateToken')->once()->andReturn('fake-verification-token');
        });

        $response = $this->post(route('register'), $userData);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', __('messages.registration_successful_verify_email'));

        $this->assertDatabaseHas('users', [
            'username' => 'cooluser',
            'email' => 'user@example.com',
            'profile_picture' => 'profile_pictures/initial_user.png',
        ]);

        $user = User::where('email', 'user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->email_verification_token);
    }

    #[Test]
    public function a_user_can_register_with_a_profile_picture()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post(route('register'), [
            'first_name' => 'User',
            'username' => 'cooluser',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'profile_picture' => $file,
            'terms' => 'on',
        ]);

        $response->assertRedirect(route('login'));
        $user = User::where('email', 'user@example.com')->first();
        $this->assertNotNull($user);
        Storage::disk('public')->assertExists($user->profile_picture);
    }

    #[Test]
    public function registration_fails_with_invalid_data()
    {
        $response = $this->post(route('register'), [
            'first_name' => 'User',
            'username' => 'user', // Too short
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
            'terms' => 'off', // Not accepted
        ]);

        $response->assertSessionHasErrors(['username', 'email', 'password', 'terms']);
        $this->assertDatabaseMissing('users', ['first_name' => 'John']);
    }

//    Login & Logout Tests
    #[Test]
    public function login_screen_can_be_rendered()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee(__('messages.login'));
    }

    #[Test]
    public function a_verified_user_can_login_with_email()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'login_identifier' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function an_unverified_user_cannot_login()
    {
        User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => null,
        ]);

        $response = $this->post(route('login'), [
            'login_identifier' => 'unverified@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login_identifier');
        $this->assertGuest();
    }

    #[Test]
    public function an_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

//    Email Verification Tests
    #[Test]
    public function email_can_be_verified_with_a_valid_link()
    {
        $user = User::factory()->unverified()->create();
        $service = new EmailVerificationService();
        $verificationUrl = $service->generateVerificationUrl($user);

        $this->actingAs($user)->get($verificationUrl);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(UserRegistered::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    #[Test]
    public function resending_verification_email_works()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'));

        Mail::assertSent(EmailVerification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

//    Google Socialite Tests
    #[Test]
    public function it_redirects_to_google_for_authentication()
    {
        $response = $this->get(route('auth.google'));
        $response->assertRedirect();
        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/v2/auth', $response->getTargetUrl());
    }

    #[Test]
    public function it_handles_google_callback_and_registers_a_new_user()
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('12345');
        $googleUser->shouldReceive('getEmail')->andReturn('newgoogleuser@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Google User');
        $googleUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar.jpg');
        $googleUser->user = ['given_name' => 'Google', 'family_name' => 'User'];

        Socialite::shouldReceive('driver->stateless->user')->andReturn($googleUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('users', [
            'email' => 'newgoogleuser@example.com',
            'google_id' => '12345',
        ]);
        $this->assertAuthenticated();
        Event::assertDispatched(UserRegistered::class);
    }

    #[Test]
    public function it_handles_google_callback_and_logs_in_an_existing_user()
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => '12345',
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('12345');
        $googleUser->shouldReceive('getEmail')->andReturn('existing@example.com');

        Socialite::shouldReceive('driver->stateless->user')->andReturn($googleUser);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1); // No new user created
        Event::assertNotDispatched(UserRegistered::class);
    }
}
