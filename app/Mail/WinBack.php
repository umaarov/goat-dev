<?php

namespace App\Mail;

use App\Models\Post;
use App\Models\User;
use App\Support\MailText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class WinBack extends Mailable implements ShouldQueue
{
    public array $items;

    public string $unsubscribeUrl;

    public function __construct(public User $user, Collection $posts)
    {
        $this->items = $posts->map(function (Post $post) {
            $post->loadMissing('user:id,username');

            return [
                'question' => $post->question,
                'one' => $post->option_one_title,
                'two' => $post->option_two_title,
                'votes' => (int) $post->total_votes,
                'url' => route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id, 'utm_source' => 'email', 'utm_medium' => 'email', 'utm_campaign' => 'winback']),
            ];
        })->all();
        $this->unsubscribeUrl = URL::signedRoute('notifications.email.unsubscribe', ['user' => $user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.winback.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.winback',
            text: 'emails.text.winback',
            with: [
                'name' => MailText::name($this->user),
                'items' => $this->items,
                'homeUrl' => route('home', ['utm_source' => 'email', 'utm_medium' => 'email', 'utm_campaign' => 'winback']),
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'preferencesUrl' => route('profile.edit'),
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
