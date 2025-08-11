<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnsubscribedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $ipAddress;

    public function __construct(User $user, string $ipAddress)
    {
        $this->user = $user;
        $this->ipAddress = $ipAddress;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation: You Have Been Unsubscribed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.notifications.unsubscribed',
        );
    }
}
