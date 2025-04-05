<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with(['user:id,username,profile_picture', 'comments.user:id,username,profile_picture'])
            ->withCount(['comments', 'shares']);

        // Apply filter if provided
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'trending':
                    $query->orderBy('total_votes', 'desc');
                    break;
                case 'latest':
                    $query->latest();
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        $posts = $query->paginate(15);

        // Add user vote information if authenticated
        if ($request->user()) {
            $posts->getCollection()->transform(function ($post) use ($request) {
                $vote = Vote::where('user_id', $request->user()->id)
                    ->where('post_id', $post->id)
                    ->first();

                $post->user_vote = $vote ? $vote->vote_option : null;
                $post->is_saved = $request->user()->savedPosts()->where('post_id', $post->id)->exists();

                return $post;
            });
        }

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:100',
            'option_one_image' => 'nullable|image|max:2048',
            'option_two_title' => 'required|string|max:100',
            'option_two_image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $optionOneImagePath = null;
        if ($request->hasFile('option_one_image')) {
            $optionOneImagePath = $request->file('option_one_image')->store('post_images', 'public');
        }

        $optionTwoImagePath = null;
        if ($request->hasFile('option_two_image')) {
            $optionTwoImagePath = $request->file('option_two_image')->store('post_images', 'public');
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $optionOneImagePath,
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $optionTwoImagePath,
        ]);


        return response()->json($post->load('user:id,username,profile_picture'), 201);
    }

    public function show(Post $post, Request $request)
    {
        $post->load(['user:id,username,profile_picture', 'comments.user:id,username,profile_picture'])
            ->loadCount(['comments', 'shares']);

        // Add user vote information if authenticated
        if ($request->user()) {
            $vote = Vote::where('user_id', $request->user()->id)
                ->where('post_id', $post->id)
                ->first();

            $post->user_vote = $vote ? $vote->vote_option : null;
            $post->is_saved = $request->user()->savedPosts()->where('post_id', $post->id)->exists();
        }

        return response()->json($post);
    }

    public function update(Request $request, Post $post)
    {
        // Check if the authenticated user is the post owner
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:100',
            'option_one_image' => 'nullable|image|max:2048',
            'option_two_title' => 'required|string|max:100',
            'option_two_image' => 'nullable|image|max:2048',
            'remove_option_one_image' => 'nullable|boolean',
            'remove_option_two_image' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the post has votes - if it does, certain updates are restricted
        if ($post->total_votes > 0) {
            return response()->json(['message' => 'Cannot update a post that has already received votes'], 403);
        }

        $data = [
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_two_title' => $request->option_two_title,
        ];

        // Handle option one image
        if ($request->hasFile('option_one_image')) {
            // Delete old image if exists
            if ($post->option_one_image) {
                Storage::disk('public')->delete($post->option_one_image);
            }
            $data['option_one_image'] = $request->file('option_one_image')->store('post_images', 'public');
        } elseif ($request->remove_option_one_image && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
        }

        // Handle option two image
        if ($request->hasFile('option_two_image')) {
            // Delete old image if exists
            if ($post->option_two_image) {
                Storage::disk('public')->delete($post->option_two_image);
            }
            $data['option_two_image'] = $request->file('option_two_image')->store('post_images', 'public');
        } elseif ($request->remove_option_two_image && $post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
            $data['option_two_image'] = null;
        }

        $post->update($data);

        return response()->json($post->load('user:id,username,profile_picture'));
    }

    public function destroy(Post $post, Request $request)
    {
        // Check if the authenticated user is the post owner
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated images if they exist
        if ($post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
        }
        if ($post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function vote(Post $post, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user has already voted on this post
        $existingVote = Vote::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            return response()->json(['message' => 'You have already voted on this post'], 403);
        }

        // Create the vote
        $vote = Vote::create([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'vote_option' => $request->option,
        ]);

        // Update the post vote counts
        if ($request->option === 'option_one') {
            $post->increment('option_one_votes');
        } else {
            $post->increment('option_two_votes');
        }
        $post->increment('total_votes');

        // Refresh the post model
        $post->refresh()->load('user:id,username,profile_picture');
        $post->user_vote = $request->option;

        return response()->json($post);
    }

    public function savePost(Post $post, Request $request)
    {
        // Check if post is already saved
        $isSaved = $request->user()->savedPosts()->where('post_id', $post->id)->exists();

        if ($isSaved) {
            return response()->json(['message' => 'Post is already saved'], 400);
        }

        // Save the post
        $request->user()->savedPosts()->attach($post->id);

        return response()->json(['message' => 'Post saved successfully']);
    }

    public function unsavePost(Post $post, Request $request)
    {
        // Remove the saved post
        $request->user()->savedPosts()->detach($post->id);

        return response()->json(['message' => 'Post removed from saved']);
    }

    public function getUserPosts(Request $request)
    {
        $posts = Post::where('user_id', $request->user()->id)
            ->with(['user:id,username,profile_picture'])
            ->withCount(['comments', 'shares'])
            ->latest()
            ->paginate(15);

        // Add user vote information
        $posts->getCollection()->transform(function ($post) use ($request) {
            $vote = Vote::where('user_id', $request->user()->id)
                ->where('post_id', $post->id)
                ->first();

            $post->user_vote = $vote ? $vote->vote_option : null;
            $post->is_saved = true; // User's own posts are considered "saved"

            return $post;
        });

        return response()->json($posts);
    }

    public function getSavedPosts(Request $request)
    {
        $posts = $request->user()->savedPosts()
            ->with(['user:id,username,profile_picture'])->withCount(['comments', 'shares'])
            ->latest('user_saved_posts.created_at')
            ->paginate(15);

        // Add user vote information
        $posts->getCollection()->transform(function ($post) use ($request) {
            $vote = Vote::where('user_id', $request->user()->id)
                ->where('post_id', $post->id)
                ->first();

            $post->user_vote = $vote ? $vote->vote_option : null;
            $post->is_saved = true; // These are saved posts

            return $post;
        });

        return response()->json($posts);
    }

    public function getVotedPosts(Request $request)
    {
        $posts = $request->user()->votedPosts()
            ->with(['user:id,username,profile_picture'])
            ->withCount(['comments', 'shares'])
            ->latest('votes.created_at')
            ->paginate(15);

        // Add user vote information
        $posts->getCollection()->transform(function ($post) use ($request) {
            $post->user_vote = $post->pivot->vote_option;
            $post->is_saved = $request->user()->savedPosts()->where('post_id', $post->id)->exists();

            return $post;
        });

        return response()->json($posts);
    }
}

