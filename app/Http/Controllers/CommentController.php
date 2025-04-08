<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    final function store(Request $request, Post $post): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to add comment. Please check the errors.');
        }

        Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'content' => $request->content,
        ]);

        // Redirect back to the post page, potentially with a fragment identifier
        return redirect()->route('posts.show', $post->id) . '#comments'
            ->with('success', 'Comment added successfully!');
    }

    // final function edit(Comment $comment): View|RedirectResponse
    // {
    //     if (Auth::id() !== $comment->user_id) {
    //         abort(403, 'Unauthorized action.');
    //     }
    //     // Optional: Check if comments are still editable (e.g., within X minutes)
    //     return view('comments.edit', compact('comment')); // Assumes view at resources/views/comments/edit.blade.php
    // }

    final function update(Request $request, Comment $comment): RedirectResponse
    {
        if (Auth::id() !== $comment->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to update comment.');
        }

        $comment->update([
            'content' => $request->content,
        ]);

        return redirect()->route('posts.show', $comment->post_id) . '#comment-' . $comment->id // Go to the specific comment
            ->with('success', 'Comment updated successfully!');
    }

    final function destroy(Comment $comment): RedirectResponse
    {
        $postOwnerId = $comment->post->user_id;
        if (Auth::id() !== $comment->user_id && Auth::id() !== $postOwnerId /* && !Auth::user()->isAdmin() */) {
            abort(403, 'Unauthorized action.');
        }

        $postId = $comment->post_id;
        $comment->delete();

        return redirect()->route('posts.show', $postId) . '#comments'
                   ->with('success', 'Comment deleted successfully.');
    }
}
