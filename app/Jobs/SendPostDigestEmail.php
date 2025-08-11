<?php
namespace App\Jobs;

use App\Mail\NewPostsNotification;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPostDigestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function handle(): void
    {
        $user = User::find($this->user->id);

        if (!$user || !$user->receives_notifications) {
            Log::info("Skipping post digest for user {$this->user->id} because they have unsubscribed or were deleted.");
            return;
        }

        $since = $user->last_notified_at ?? Carbon::now()->subDays(2);
        $newPosts = Post::where('created_at', '>', $since)->latest()->get();

        if ($newPosts->isEmpty()) {
            return;
        }

        $mainPost = $newPosts->sortByDesc('total_votes')->first();
        $gridPosts = $newPosts->where('id', '!=', $mainPost->id)->take(4);

        try {
            Mail::to($user)->send(new NewPostsNotification($user, $mainPost, $gridPosts));

            $user->last_notified_at = Carbon::now();
            $user->save();
        } catch (Exception $e) {
            Log::error("Failed to queue post digest for user {$user->id}: " . $e->getMessage());
            $this->release(300);
        }
    }
}
