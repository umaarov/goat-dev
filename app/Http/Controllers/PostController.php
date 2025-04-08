<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    final function index(Request $request): View
    {
        $query = Post::with([
            'user:id,username,profile_picture',
//            'voters:id,username,profile_picture'
        ])
            ->withCount(['comments', 'shares', 'voters']);

        switch ($request->input('filter')) {
            case 'trending':
                $query->where('created_at', '>=', now()->subDays(7))
                    ->orderByDesc('total_votes')
                    ->orderByDesc('created_at');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
            // Add more filters like 'popular' (all time votes), 'most_commented' etc.
        }

        $posts = $query->paginate(15)->withQueryString();

        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id');

            $userVotes = Vote::where('user_id', $loggedInUserId)
                ->whereIn('post_id', $postIds)
                ->pluck('vote_option', 'post_id');

            $posts->getCollection()->transform(function ($post) use ($userVotes) {
                $post->user_vote = $userVotes->get($post->id);
                return $post;
            });
        } else {
            $posts->getCollection()->transform(function ($post) {
                $post->user_vote = null;
                return $post;
            });
        }

        return view('posts.index', compact('posts'));
    }

    final function create(): View
    {
        return view('posts.create');
    }

    final function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:100',
            'option_one_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'option_two_title' => 'required|string|max:100',
            'option_two_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
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
            'user_id' => Auth::id(),
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $optionOneImagePath,
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $optionTwoImagePath,
        ]);

        return redirect()->route('posts.show', $post)->with('success', 'Post created successfully!');
    }

    final function show(Post $post): View
    {
        $post->increment('view_count');

        $post->load([
            'user:id,username,profile_picture',
            'comments' => function ($query) {
                $query->with('user:id,username,profile_picture')->latest();
            },
            'voters:id,username,profile_picture'
        ])->loadCount(['comments', 'shares']);

        $post->user_vote = null;
        if (Auth::check()) {
            $vote = Vote::where('user_id', Auth::id())
                ->where('post_id', $post->id)
                ->first();
            $post->user_vote = $vote ? $vote->vote_option : null;
        }

        return view('posts.show', compact('post'));
    }

    final function edit(Post $post): View|RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($post->total_votes > 0) {
            return redirect()->route('posts.show', $post)->with('error', 'Cannot edit a post that has already received votes.');
        }


        return view('posts.edit', compact('post'));
    }

    final function update(Request $request, Post $post): RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($post->total_votes > 0) {
            return redirect()->route('posts.show', $post)->with('error', 'Cannot update a post that has already received votes.');
        }


        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:100',
            'option_one_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'option_two_title' => 'required|string|max:100',
            'option_two_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_option_one_image' => 'nullable|boolean',
            'remove_option_two_image' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }


        $data = [
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_two_title' => $request->option_two_title,
        ];

        if ($request->boolean('remove_option_one_image') && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
        } elseif ($request->hasFile('option_one_image')) {
            if ($post->option_one_image) {
                Storage::disk('public')->delete($post->option_one_image);
            }
            $data['option_one_image'] = $request->file('option_one_image')->store('post_images', 'public');
        }

        if ($request->boolean('remove_option_two_image') && $post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
            $data['option_two_image'] = null;
        } elseif ($request->hasFile('option_two_image')) {
            if ($post->option_two_image) {
                Storage::disk('public')->delete($post->option_two_image);
            }
            $data['option_two_image'] = $request->file('option_two_image')->store('post_images', 'public');
        }

        $post->update($data);

        return redirect()->route('posts.show', $post)->with('success', 'Post updated successfully.');
    }

    final function destroy(Post $post): RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
        }
        if ($post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
        }

        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Post deleted successfully.');
    }

    final function vote(Request $request, Post $post): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $loggedInUserId = Auth::id();

        $existingVote = Vote::where('user_id', $loggedInUserId)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            return redirect()->back()->with('error', 'You have already voted on this post.');
            // if ($existingVote->vote_option !== $request->option) {
            //     // Decrement old count, increment new count, update vote record
            // } else {
            //     return redirect()->back()->with('info', 'Your vote remains the same.');
            // }
        }

        Vote::create([
            'user_id' => $loggedInUserId,
            'post_id' => $post->id,
            'vote_option' => $request->option,
        ]);

        if ($request->option === 'option_one') {
            $post->increment('option_one_votes');
        } else {
            $post->increment('option_two_votes');
        }
        $post->increment('total_votes');

        return redirect()->route('posts.show', $post)->with('success', 'Your vote has been registered!');
        // return redirect()->back()->with('success', 'Vote registered!');
    }
}
