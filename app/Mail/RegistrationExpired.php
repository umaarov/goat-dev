<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RegistrationExpired extends Mailable
{
    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.expired.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-expired',
            text: 'emails.text.registration-expired',
            with: [
                'name' => MailText::name($this->user),
                'url' => route('register'),
                'time' => MailText::duration(EmailVerificationService::TTL_MINUTES),
            ],
        );
    }
}
