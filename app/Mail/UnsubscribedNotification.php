<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class UnsubscribedNotification extends Mailable implements ShouldQueue
{
    public function __construct(public User $user, public string $ipAddress)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.unsubscribed.subject'));
    }

    public function content(): Content
    {
        $when = now()->tz(config('app.timezone'))->locale(MailText::carbonLocale())->translatedFormat('j F Y, H:i').' ('.config('app.timezone').')';

        return new Content(
            view: 'emails.notifications.unsubscribed',
            text: 'emails.text.unsubscribed',
            with: [
                'name' => MailText::name($this->user),
                'when' => $when,
                'ip' => $this->ipAddress,
                // the same signed page: it shows "turn updates back on" while they are off
                'resubscribeUrl' => URL::signedRoute('notifications.email.unsubscribe', ['user' => $this->user->id]),
            ],
        );
    }
}
