<?php

namespace App\Http\Controllers; // Changed Namespace

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
    /**
     * Display a listing of the posts.
     */
    final function index(Request $request): View
    {
        $query = Post::with([ // Eager load common relationships
            'user:id,username,profile_picture',
            // Load voters count instead of the full list for index pages
            // 'voters:id,username,profile_picture'
        ])
            ->withCount(['comments', 'shares', 'voters']); // Eager load counts

        // Apply filters
        switch ($request->input('filter')) {
            case 'trending':
                // Simple trending: order by votes in a recent period (e.g., last 7 days)
                // For more complex trending, consider a dedicated score calculation
                $query->where('created_at', '>=', now()->subDays(7))
                    ->orderByDesc('total_votes')
                    ->orderByDesc('created_at');
                break;
            case 'latest':
            default:
                $query->latest(); // Order by creation date, newest first
                break;
            // Add more filters like 'popular' (all time votes), 'most_commented' etc.
        }

        $posts = $query->paginate(15)->withQueryString(); // Paginate and keep filter parameters

        // Determine if the logged-in user voted on these posts
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

        return view('posts.index', compact('posts')); // Assumes view at resources/views/posts/index.blade.php
    }

    /**
     * Show the form for creating a new post.
     */
    final function create(): View
    {
        return view('posts.create'); // Assumes view at resources/views/posts/create.blade.php
    }


    /**
     * Store a newly created post in storage.
     */
    final function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:100',
            'option_one_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Be specific with mimes
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
            'user_id' => Auth::id(), // Use logged-in user's ID
            'question' => $request->question,
            'option_one_title' => $request->option_one_title,
            'option_one_image' => $optionOneImagePath,
            'option_two_title' => $request->option_two_title,
            'option_two_image' => $optionTwoImagePath,
        ]);

        // Redirect to the newly created post's page
        return redirect()->route('posts.show', $post)->with('success', 'Post created successfully!');
    }

    /**
     * Display the specified post.
     */
    final function show(Post $post): View // Use route model binding
    {
        // Increment view count (consider rate limiting or uniqueness if needed)
        $post->increment('view_count');

        // Load necessary relationships for the detail view
        $post->load([
            'user:id,username,profile_picture',
            'comments' => function ($query) { // Load comments with their users, ordered
                $query->with('user:id,username,profile_picture')->latest();
            },
            'voters:id,username,profile_picture' // Load users who voted
        ])->loadCount(['comments', 'shares']); // Load counts

        // Determine if the logged-in user voted on this post
        $post->user_vote = null;
        if (Auth::check()) {
            $vote = Vote::where('user_id', Auth::id())
                ->where('post_id', $post->id)
                ->first();
            $post->user_vote = $vote ? $vote->vote_option : null;
        }

        return view('posts.show', compact('post')); // Assumes view at resources/views/posts/show.blade.php
    }

    /**
     * Show the form for editing the specified post.
     */
    final function edit(Post $post): View|RedirectResponse // Use route model binding
    {
        // Authorization check: Only the owner can edit
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        // Prevent editing if votes exist
        if ($post->total_votes > 0) {
            return redirect()->route('posts.show', $post)->with('error', 'Cannot edit a post that has already received votes.');
        }


        return view('posts.edit', compact('post')); // Assumes view at resources/views/posts/edit.blade.php
    }

    /**
     * Update the specified post in storage.
     */
    final function update(Request $request, Post $post): RedirectResponse // Use route model binding
    {
        // Authorization check
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        // Prevent editing if votes exist (double check)
        if ($post->total_votes > 0) {
            return redirect()->route('posts.show', $post)->with('error', 'Cannot update a post that has already received votes.');
        }


        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'option_one_title' => 'required|string|max:100',
            'option_one_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'option_two_title' => 'required|string|max:100',
            'option_two_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_option_one_image' => 'nullable|boolean', // Checkbox/hidden input to signal removal
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

        // Handle Option One Image Update/Removal
        if ($request->boolean('remove_option_one_image') && $post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
            $data['option_one_image'] = null;
        } elseif ($request->hasFile('option_one_image')) {
            // Delete old if exists before storing new
            if ($post->option_one_image) {
                Storage::disk('public')->delete($post->option_one_image);
            }
            $data['option_one_image'] = $request->file('option_one_image')->store('post_images', 'public');
        }

        // Handle Option Two Image Update/Removal
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

    /**
     * Remove the specified post from storage.
     */
    final function destroy(Post $post): RedirectResponse // Use route model binding
    {
        // Authorization check
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        // Delete associated images from storage
        if ($post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
        }
        if ($post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
        }

        $post->delete(); // This should cascade delete related votes, comments via model events or DB constraints

        return redirect()->route('posts.index')->with('success', 'Post deleted successfully.');
    }

    /**
     * Record a vote for the specified post.
     */
    final function vote(Request $request, Post $post): RedirectResponse // Use route model binding
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            // Usually voting is done via JS, but for non-JS fallback:
            return redirect()->back()->withErrors($validator);
        }

        $loggedInUserId = Auth::id();

        // Check if user already voted
        $existingVote = Vote::where('user_id', $loggedInUserId)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            // Optionally allow changing vote, or prevent voting again
            return redirect()->back()->with('error', 'You have already voted on this post.');
            // If allowing vote change:
            // if ($existingVote->vote_option !== $request->option) {
            //     // Decrement old count, increment new count, update vote record
            // } else {
            //     return redirect()->back()->with('info', 'Your vote remains the same.');
            // }
        }

        // Record the new vote
        Vote::create([
            'user_id' => $loggedInUserId,
            'post_id' => $post->id,
            'vote_option' => $request->option,
        ]);

        // Update post vote counts
        if ($request->option === 'option_one') {
            $post->increment('option_one_votes');
        } else {
            $post->increment('option_two_votes');
        }
        $post->increment('total_votes'); // Update total votes as well

        // Redirect back to the post page
        return redirect()->route('posts.show', $post)->with('success', 'Your vote has been registered!');
        // Or just redirect back if voting happens on the index page:
        // return redirect()->back()->with('success', 'Vote registered!');
    }
}
