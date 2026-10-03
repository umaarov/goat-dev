<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeMessage extends Mailable
{
    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.welcome.subject', ['name' => MailText::name($this->user)]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.welcome',
            text: 'emails.text.welcome',
            with: [
                'name' => MailText::name($this->user),
                'trendingUrl' => route('home'),
                'askUrl' => route('posts.create'),
                'profileUrl' => route('profile.edit'),
                'preferencesUrl' => route('profile.edit'),
            ],
        );
    }
}
