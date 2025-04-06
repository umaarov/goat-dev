<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    final function index(Request $request): JsonResponse
    {
        $query = Post::with([
            'user:id,username,profile_picture',
            'comments.user:id,username,profile_picture',
            'voters:id,username,profile_picture'
        ])->withCount(['comments', 'shares']);

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

        if ($request->user()) {
            $posts->getCollection()->transform(function ($post) use ($request) {
                $vote = Vote::where('user_id', $request->user()->id)
                    ->where('post_id', $post->id)
                    ->first();

                $post->user_vote = $vote ? $vote->vote_option : null;

                return $post;
            });
        }

        return response()->json($posts);
    }

    final function store(Request $request): JsonResponse
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

    final function show(Post $post, Request $request): JsonResponse
    {
        $post->increment('view_count');

        $post->load([
            'user:id,username,profile_picture',
            'comments.user:id,username,profile_picture',
            'voters:id,username,profile_picture'
        ])->loadCount(['comments', 'shares']);

        if ($request->user()) {
            $vote = Vote::where('user_id', $request->user()->id)
                ->where('post_id', $post->id)
                ->first();

            $post->user_vote = $vote ? $vote->vote_option : null;
        }

        return response()->json($post);
    }

    final function update(Request $request, Post $post): JsonResponse
    {
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

        if ($post->total_votes > 0) {
            return response()->json(['message' => 'Cannot update a post that has already received votes'], 403);
        }

        $data = [
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_two_title' => $request->option_two_title,
        ];

        if ($request->hasFile('option_one_image')) {
            if ($post->option_one_image) {
                Storage::disk('public')->delete($post->option_one_image);
            }
            $data['option_one_image'] = $request->file('option_one_image')->store('post_images', 'public');
        } elseif ($request->remove_option_one_image && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
        }

        if ($request->hasFile('option_two_image')) {
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

    final function destroy(Post $post, Request $request): JsonResponse
    {
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
        }
        if ($post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    final function vote(Post $post, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existingVote = Vote::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            return response()->json(['message' => 'You have already voted on this post'], 403);
        }

//        $vote = Vote::create([
//            'user_id' => $request->user()->id,
//            'post_id' => $post->id,
//            'vote_option' => $request->option,
//        ]);

        if ($request->option === 'option_one') {
            $post->increment('option_one_votes');
        } else {
            $post->increment('option_two_votes');
        }
        $post->increment('total_votes');

        $post->refresh()->load([
            'user:id,username,profile_picture',
            'voters:id,username,profile_picture'
        ]);
        $post->user_vote = $request->option;

        return response()->json($post);
    }

    public function getUserPosts(Request $request)
    {
        $posts = Post::where('user_id', $request->user()->id)
            ->with([
                'user:id,username,profile_picture',
                'voters:id,username,profile_picture'
            ])
            ->withCount(['comments', 'shares'])
            ->latest()
            ->paginate(15);

        $posts->getCollection()->transform(function ($post) use ($request) {
            $vote = Vote::where('user_id', $request->user()->id)
                ->where('post_id', $post->id)
                ->first();

            $post->user_vote = $vote ? $vote->vote_option : null;
            return $post;
        });

        return response()->json($posts);
    }

    final function getVotedPosts(Request $request): JsonResponse
    {
        $posts = $request->user()->votedPosts()
            ->with([
                'user:id,username,profile_picture',
                'voters:id,username,profile_picture'
            ])
            ->withCount(['comments', 'shares'])
            ->latest('votes.created_at')
            ->paginate(15);

        $posts->getCollection()->transform(function ($post) use ($request) {
            $post->user_vote = $post->pivot->vote_option;

            return $post;
        });

        return response()->json($posts);
    }
}

