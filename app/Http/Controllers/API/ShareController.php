<?php

namespace App\Http\Controllers; // Changed Namespace

use App\Models\Post;
use App\Models\Share;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShareController extends Controller
{
    /**
     * Record that a post was shared on a specific platform.
     * This doesn't perform the actual share, just logs it.
     */
    final function store(Request $request, Post $post): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            // Validate the platform if necessary, e.g., ensure it's one you track
            'platform' => 'required|string|in:twitter,facebook,whatsapp,telegram,email,link_copy',
        ]);

        if ($validator->fails()) {
            // Less critical if this fails, maybe just ignore or log?
            // Or redirect back with an error if feedback is desired.
            return redirect()->back()->with('error', 'Invalid share platform specified.');
        }

        // Check if user is logged in before associating share with user
        $userId = Auth::check() ? Auth::id() : null;

        Share::create([
            'user_id' => $userId, // Can be null if guest sharing is tracked
            'post_id' => $post->id,
            'platform' => $request->platform,
        ]);

        // Increment share count on the post model
        $post->increment('shares_count'); // Assumes you renamed the relationship count column or use withCount

        // No explicit success message needed usually, as this is often an async background action
        // Just redirect back to where the user was.
        return redirect()->back()->with('info', 'Share recorded.'); // Optional feedback
    }
}
