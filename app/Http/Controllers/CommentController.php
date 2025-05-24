<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function index(Request $request, Post $post)
    {
        $perPage = $request->input('per_page', 10);
        $comments = Comment::where('post_id', $post->id)
            ->with('user:id,username,profile_picture')
            ->with('post:id,user_id')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'comments' => $comments
        ]);
    }

    final public function store(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to add comment. Please check the errors.');
        }
        $user = Auth::user();

        $content = $request->input('content');
        if (empty($content)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['errors' => ['content' => 'Content cannot be empty.']], 422);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'Content cannot be empty.');
        }

        $comment = Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'content' => $content,
        ]);

        Log::channel('audit_trail')->info('Comment created.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'comment_id' => $comment->id,
            'post_id' => $post->id,
            'comment_snippet' => Str::limit($comment->content, 100),
            'ip_address' => $request->ip(),
        ]);

        $comment->load('user:id,username,profile_picture');
        $comment->load('post:id,user_id');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Comment added successfully!',
                'comment' => $comment
            ], 201);
        }

        return redirect()->back()->with('success', 'Comment added successfully!');
    }

    final public function update(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if (Auth::id() !== $comment->user_id) {
            Log::channel('audit_trail')->warning('Unauthorized comment update attempt.', [
                'attempting_user_id' => $user->id,
                'attempting_username' => $user->username,
                'comment_id' => $comment->id,
                'comment_owner_id' => $comment->user_id,
                'ip_address' => $request->ip(),
            ]);
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

        Log::channel('audit_trail')->info('Comment updated.', [
            'user_id' => $user->id,
            'username' => $user->username,
            'comment_id' => $comment->id,
            'post_id' => $comment->post_id,
            'ip_address' => $request->ip(),
        ]);

        if ($request->expectsJson()) {
            $comment->load('user:id,username,profile_picture');
            return response()->json([
                'message' => 'Comment updated successfully!',
                'comment' => $comment
            ]);
        }

        return redirect()->back()->with('success', 'Comment updated successfully!');
    }

    final public function destroy(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $postOwnerId = $comment->post->user_id;
        if (Auth::id() !== $comment->user_id && Auth::id() !== $postOwnerId /* && !Auth::user()->isAdmin() */) {
            Log::channel('audit_trail')->warning('Unauthorized comment deletion attempt.', [
                'attempting_user_id' => $user->id,
                'attempting_username' => $user->username,
                'comment_id' => $comment->id,
                'comment_owner_id' => $comment->user_id,
                'post_owner_id' => $postOwnerId,
                'ip_address' => $request->ip(),
            ]);
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized action.');
        }

        $commentId = $comment->id;
        $originalCommenterId = $comment->user_id;
        $commentSnippet = Str::limit($comment->content, 100);
        $postId = $comment->post_id;
        $comment->delete();
        Log::channel('audit_trail')->info('Comment deleted.', [
            'deleter_user_id' => $user->id,
            'deleter_username' => $user->username,
            'deleted_comment_id' => $commentId,
            'original_commenter_id' => $originalCommenterId,
            'original_comment_snippet' => $commentSnippet,
            'post_id' => $postId,
            'ip_address' => $request->ip(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Comment deleted successfully!']);
        }

        return redirect()->back()->with('success', 'Comment deleted successfully.');
    }
}
