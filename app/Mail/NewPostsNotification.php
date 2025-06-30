<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewPostsNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public $mainPost;
    public Collection $gridPosts;
    public string $layoutVariation;

    public function __construct(User $user, $mainPost, Collection $gridPosts)
    {
        $this->user = $user;
        $this->mainPost = $mainPost;
        $this->gridPosts = $gridPosts;
        $this->layoutVariation = ['grid_2x2', 'vertical_list', 'grid_1x2'][array_rand(['grid_2x2', 'vertical_list', 'grid_1x2'])];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'See What\'s New on GOAT.uz!',);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new_posts_notification',);
    }

    public function attachments(): array
    {
        return [];
    }
}
