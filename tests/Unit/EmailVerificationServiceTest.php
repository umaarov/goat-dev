<?php

namespace Tests\Unit;

use App\Mail\EmailVerification;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_an_email_verification_mail()
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $service = new EmailVerificationService();

        $service->sendVerificationEmail($user);

        Mail::assertSent(EmailVerification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    #[Test]
    public function it_verifies_a_user_with_a_correct_token()
    {
        $user = User::factory()->unverified()->create([
            'email_verification_token' => 'correct-token'
        ]);
        $service = new EmailVerificationService();

        $result = $service->verify($user, 'correct-token');

        $this->assertTrue($result);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->email_verification_token);
    }

    #[Test]
    public function it_fails_to_verify_a_user_with_an_incorrect_token()
    {
        $user = User::factory()->unverified()->create([
            'email_verification_token' => 'correct-token'
        ]);
        $service = new EmailVerificationService();

        $result = $service->verify($user, 'incorrect-token');

        $this->assertFalse($result);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNotNull($user->fresh()->email_verification_token);
    }
}
