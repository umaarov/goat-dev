<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    final public function index(Request $request): View
    {
        $query = Post::query()->withPostData();

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
        }

        $posts = $query->paginate(15)->withQueryString();

        $this->attachUserVoteStatus($posts);

        return view('home', compact('posts'));
    }

    final public function create(): View
    {
        return view('posts.create');
    }

    final public function store(Request $request): RedirectResponse
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

        return redirect()->route('home')->with('success', 'Post created successfully!');
    }

    final public function edit(Post $post): View|RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($post->total_votes > 0) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', 'Cannot edit a post that has already received votes.');
        }

        return view('posts.edit', compact('post'));
    }

    final public function update(Request $request, Post $post): RedirectResponse
    {
        if (Auth::id() !== $post->user_id) {
            abort(403, 'Unauthorized action.');
        }

        if ($post->total_votes > 0) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('error', 'Cannot update a post that has already received votes.');
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

        $data = $request->only(['question', 'option_one_title', 'option_two_title']);

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

        return redirect()->route('profile.show', ['username' => $post->user->username])
            ->with('success', 'Post updated successfully.');
    }

    final public function destroy(Post $post): RedirectResponse
    {
        if (Auth::id() !== $post->user_id /* && !Auth::user()->isAdmin() */) {
            abort(403, 'Unauthorized action.');
        }

        if ($post->option_one_image) {
            Storage::disk('public')->delete($post->option_one_image);
        }
        if ($post->option_two_image) {
            Storage::disk('public')->delete($post->option_two_image);
        }

        $post->delete();

        if (url()->previous() == route('profile.show', ['username' => Auth::user()->username])) {
            return redirect()->route('profile.show', ['username' => Auth::user()->username])
                ->with('success', 'Post deleted successfully.');
        }

        return redirect()->route('home')->with('success', 'Post deleted successfully.');
    }

    final public function vote(Request $request, Post $post): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'option' => 'required|in:option_one,option_two',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $loggedInUserId = Auth::id();

        $existingVote = Vote::where('user_id', $loggedInUserId)
            ->where('post_id', $post->id)
            ->first();

        if ($existingVote) {
            return response()->json(['error' => 'You have already voted on this post.'], 409);
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

        $post->refresh();
        return response()->json([
            'message' => 'Vote registered successfully!',
            'option_one_votes' => $post->option_one_votes,
            'option_two_votes' => $post->option_two_votes,
            'total_votes' => $post->total_votes,
            'user_vote' => $request->option,
        ]);
    }

    public function showBySlug($id, $slug = null)
    {
        $post = Post::findOrFail($id);

        // Get the posts for the page where this post should appear
        $perPage = 10; // Match your pagination settings
        $allPosts = Post::orderBy('created_at', 'desc')->get();

        // Find the index of the current post
        $postIndex = $allPosts->search(function ($item) use ($id) {
            return $item->id == $id;
        });

        // Calculate which page this post should be on
        $page = floor($postIndex / $perPage) + 1;

        // Redirect to the posts page, with the page number and a fragment identifier
        return redirect()->route('home', ['page' => $page])
            ->with('scrollToPost', $id);
    }

    public function incrementShareCount(Post $post)
    {
        $post->increment('shares_count');
        return response()->json(['shares_count' => $post->shares()->count()]);
    }

    final public function search(Request $request): View|JsonResponse
    {
        $queryTerm = $request->input('q');

        if (!$queryTerm) {
            return view('search.results', ['posts' => collect(), 'queryTerm' => null]);
        }

        $query = Post::query()->withPostData();

        $query->where(function (Builder $subQuery) use ($queryTerm) {
            $subQuery->where('question', 'LIKE', "%{$queryTerm}%")
                ->orWhere('option_one_title', 'LIKE', "%{$queryTerm}%")
                ->orWhere('option_two_title', 'LIKE', "%{$queryTerm}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($queryTerm) {
                    $userQuery->where('username', 'LIKE', "%{$queryTerm}%");
                });
        });

        $posts = $query->latest()->paginate(15)->withQueryString();

        $this->attachUserVoteStatus($posts);

        if ($request->expectsJson()) {
            return response()->json($posts);
        }

        return view('search.results', compact('posts', 'queryTerm')); // Assumes resources/views/search/results.blade.php
    }

    private function attachUserVoteStatus(LengthAwarePaginator $posts): void
    {
        $userVoteMap = collect();
        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id')->all();

            if (!empty($postIds)) {
                $userVoteMap = Vote::where('user_id', $loggedInUserId)
                    ->whereIn('post_id', $postIds)
                    ->pluck('vote_option', 'post_id');
            }
        }

        $posts->getCollection()->transform(function ($post) use ($userVoteMap) {
            $post->user_vote = $userVoteMap->get($post->id);
            return $post;
        });
    }
}
