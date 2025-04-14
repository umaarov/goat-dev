<?php

namespace App\Services;

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class EmailVerificationService
{
    final public function generateToken(): string
    {
        return Str::random(64);
    }

    final public function generateVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'token' => $user->email_verification_token
            ]
        );
    }

    final public function sendVerificationEmail(User $user): void
    {
        if (!$user->email_verification_token) {
            $user->email_verification_token = $this->generateToken();
            $user->save();
        }

        $verificationUrl = $this->generateVerificationUrl($user);

        Mail::to($user->email)->send(new EmailVerification($user, $verificationUrl));
    }

    final public function verify(User $user, string $token): bool
    {
        if ($user->email_verification_token !== $token) {
            return false;
        }

        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        return true;
    }
}
