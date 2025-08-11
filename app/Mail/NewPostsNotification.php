<?php

namespace App\Mail;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewPostsNotification extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $layoutVariation;
    public array $mainPostData;
    public array $gridPostsData;
    public string $unsubscribeToken;

    public function __construct(User $user, Post $mainPost, Collection $gridPosts)
    {
        $this->user = $user;
        $this->layoutVariation = ['grid_2x2', 'vertical_list'][array_rand(['grid_2x2', 'vertical_list'])];
        $this->mainPostData = $this->formatPostData($mainPost);
        $this->gridPostsData = $gridPosts->map(fn($post) => $this->formatPostData($post))->all();

        $this->unsubscribeToken = Str::random(60);

        DB::table('unsubscribe_tokens')->insert([
            'user_id' => $this->user->id,
            'token' => $this->unsubscribeToken,
            'expires_at' => Carbon::now()->addDays(7),
            'created_at' => Carbon::now(),
        ]);
    }

    private function formatPostData(Post $post): array
    {
        return [
            'question' => $post->question,
            'url' => route('posts.show', $post),
            'option_one_title' => $post->option_one_title,
            'option_one_image' => asset('storage/' . $post->option_one_image),
            'option_two_title' => $post->option_two_title,
            'option_two_image' => asset('storage/' . $post->option_two_image),
            'total_votes' => $post->total_votes,
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔥 New Debates are heating up on GOAT.uz!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_posts_notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
