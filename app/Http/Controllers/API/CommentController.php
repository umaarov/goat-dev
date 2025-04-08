<?php

namespace App\Http\Controllers; // Changed Namespace

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Store a newly created comment in storage for a specific post.
     * Note: Comments are usually displayed on the Post show page.
     * This controller handles *adding*, *updating*, *deleting* comments.
     */
    final function store(Request $request, Post $post): RedirectResponse // Pass Post to associate comment
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000', // Adjust max length as needed
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to add comment. Please check the errors.'); // Optional generic error
        }

        Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'content' => $request->content,
        ]);

        // Redirect back to the post page, potentially with a fragment identifier
        return redirect()->route('posts.show', $post->id) . '#comments' // Go to #comments section
                   ->with('success', 'Comment added successfully!');
    }

    /**
     * Show the form for editing the specified comment.
     * (Often done inline with JS, but provide a dedicated page as fallback)
     */
    // final function edit(Comment $comment): View|RedirectResponse
    // {
    //     if (Auth::id() !== $comment->user_id) {
    //         abort(403, 'Unauthorized action.');
    //     }
    //     // Optional: Check if comments are still editable (e.g., within X minutes)
    //     return view('comments.edit', compact('comment')); // Assumes view at resources/views/comments/edit.blade.php
    // }


    /**
     * Update the specified comment in storage.
     * (This might be handled via AJAX/fetch in a modern UI)
     */
    final function update(Request $request, Comment $comment): RedirectResponse
    {
        // Authorization: Only the comment owner can update
        if (Auth::id() !== $comment->user_id) {
            abort(403, 'Unauthorized action.');
        }
        // Optional: Add time limit for editing here if needed

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->back() // Or redirect to edit form if you have one
            ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to update comment.');
        }

        $comment->update([
            'content' => $request->content,
        ]);

        // Redirect back to the post page where the comment resides
        return redirect()->route('posts.show', $comment->post_id) . '#comment-' . $comment->id // Go to the specific comment
            ->with('success', 'Comment updated successfully!');
    }

    /**
     * Remove the specified comment from storage.
     */
    final function destroy(Comment $comment): RedirectResponse
    {
        // Authorization: Comment owner or Post owner (or admin/moderator)
        $postOwnerId = $comment->post->user_id;
        if (Auth::id() !== $comment->user_id && Auth::id() !== $postOwnerId /* && !Auth::user()->isAdmin() */) {
            abort(403, 'Unauthorized action.');
        }

        $postId = $comment->post_id; // Get post ID before deleting comment
        $comment->delete();

        // Redirect back to the post page
        return redirect()->route('posts.show', $postId) . '#comments'
                   ->with('success', 'Comment deleted successfully.');
    }
}
