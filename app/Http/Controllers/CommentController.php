<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    final public function store(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to add comment. Please check the errors.');
        }

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'content' => $request->content,
        ]);

        $comment->load('user:id,username,profile_picture');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Comment added successfully!',
                'comment' => $comment
            ], 201);
        }

        return redirect()->back()->with('success', 'Comment added successfully!');
    }

    final public function update(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        if (Auth::id() !== $comment->user_id) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to update comment.');
        }

        $comment->update([
            'content' => $request->content,
        ]);

        if ($request->expectsJson()) {
            $comment->load('user:id,username,profile_picture');
            return response()->json([
                'message' => 'Comment updated successfully!',
                'comment' => $comment
            ]);
        }

        return redirect()->back()->with('success', 'Comment updated successfully!');
        // Or redirect to post show page with anchor:
        // return redirect()->route('posts.show', $comment->post_id) . '#comment-' . $comment->id
        //           ->with('success', 'Comment updated successfully!');
    }

    final public function destroy(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $postOwnerId = $comment->post->user_id;
        if (Auth::id() !== $comment->user_id && Auth::id() !== $postOwnerId /* && !Auth::user()->isAdmin() */) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized action.');
        }

        $comment->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Comment deleted successfully!']);
        }

        return redirect()->back()->with('success', 'Comment deleted successfully.');
    }
}
