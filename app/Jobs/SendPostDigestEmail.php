<?php

namespace App\Jobs;

use App\Mail\NewPostsNotification;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPostDigestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [60, 180, 300];

    public function __construct(public User $user)
    {
    }

    public function handle(): void
    {
        $user = $this->user->fresh();

        if (!$user || !$user->receives_notifications) {
            return;
        }

        $since = $user->last_notified_at ?? Carbon::now()->subDays(2);
        $newPosts = Post::where('created_at', '>', $since)->latest()->get();

        if ($newPosts->isEmpty()) {
            return;
        }

        $mainPost = $newPosts->sortByDesc('total_votes')->first();
        $gridPosts = $newPosts->where('id', '!=', $mainPost->id)->take(4);

        Mail::to($user)->send(new NewPostsNotification($user, $mainPost, $gridPosts));

        $user->last_notified_at = Carbon::now();
        $user->save();
    }
}
