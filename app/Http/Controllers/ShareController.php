<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Share;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShareController extends Controller
{
    final function store(Request $request, Post $post): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:twitter,facebook,whatsapp,telegram,email,link_copy',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Invalid share platform specified.');
        }

        $userId = Auth::check() ? Auth::id() : null;

        Share::create([
            'user_id' => $userId,
            'post_id' => $post->id,
            'platform' => $request->platform,
        ]);

        $post->increment('shares_count');

        return redirect()->back()->with('info', 'Share recorded.');
    }
}
