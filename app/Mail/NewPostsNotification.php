<?php

namespace App\Mail;

use App\Models\Post;
use App\Models\User;
use App\Services\PostCardImage;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class NewPostsNotification extends Mailable
{
    public array $main;

    public array $more;

    public string $unsubscribeUrl;

    public function __construct(public User $user, Post $mainPost, Collection $gridPosts)
    {
        $this->main = $this->format($mainPost);
        $this->more = $gridPosts->map(fn (Post $post) => $this->format($post))->all();
        // signed, so it needs no stored token and never expires; the page asks before it does anything
        $this->unsubscribeUrl = URL::signedRoute('notifications.email.unsubscribe', ['user' => $user->id]);
    }

    private function format(Post $post): array
    {
        $post->loadMissing('user:id,username');
        $route = ['username' => $post->user->username, 'post' => $post->id];

        return [
            'question' => $post->question,
            'url' => route('posts.show.user-scoped', $route),
            // a JPEG of both options and the live split: every mail client can show it (WebP photos are not universal)
            'card' => route('posts.card', $route + ['v' => app(PostCardImage::class)->version($post)]),
            'one' => $post->option_one_title,
            'two' => $post->option_two_title,
            'votes' => (int) $post->total_votes,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.digest.subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_posts_notification',
            text: 'emails.text.digest',
            with: [
                'name' => MailText::name($this->user),
                'main' => $this->main,
                'more' => $this->more,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'preferencesUrl' => route('profile.edit'),
            ],
        );
    }

    // RFC 8058 one-click unsubscribe: Gmail and Yahoo require it from bulk senders
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
