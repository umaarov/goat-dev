<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailVerification extends Mailable
{
    public function __construct(public User $user, public string $verificationUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.verify.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification',
            text: 'emails.text.verification',
            with: [
                'name' => MailText::name($this->user),
                'url' => $this->verificationUrl,
                'time' => MailText::duration(EmailVerificationService::TTL_MINUTES),
            ],
        );
    }
}
