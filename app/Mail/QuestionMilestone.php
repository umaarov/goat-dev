<?php

namespace App\Mail;

use App\Models\Post;
use App\Models\User;
use App\Services\PostCardImage;
use App\Support\MailText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

class QuestionMilestone extends Mailable implements ShouldQueue
{
    public string $unsubscribeUrl;

    public function __construct(public User $author, public Post $post, public int $milestone)
    {
        $this->unsubscribeUrl = URL::signedRoute('notifications.email.unsubscribe', ['user' => $author->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.milestone.subject', ['count' => number_format($this->milestone)]));
    }

    public function content(): Content
    {
        $route = ['username' => $this->author->username, 'post' => $this->post->id];

        return new Content(
            view: 'emails.milestone',
            text: 'emails.text.milestone',
            with: [
                'name' => MailText::name($this->author),
                'count' => number_format($this->milestone),
                'question' => $this->post->question,
                'one' => $this->post->option_one_title,
                'two' => $this->post->option_two_title,
                'pctOne' => $this->post->option_one_percentage,
                'pctTwo' => $this->post->option_two_percentage,
                'card' => route('posts.card', $route + ['v' => app(PostCardImage::class)->version($this->post)]),
                'url' => route('posts.show.user-scoped', $route + ['utm_source' => 'email', 'utm_medium' => 'email', 'utm_campaign' => 'milestone']),
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
