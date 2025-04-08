<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    final public function showProfile(string $username): View
    {
        $user = User::where('username', $username)
            ->select(['id', 'first_name', 'last_name', 'username', 'profile_picture', 'created_at'])
            ->firstOrFail();

        // Basic stats - can be loaded dynamically or here
        // $user->posts_count = $user->posts()->count();
        // $user->votes_received = $user->posts()->sum('total_votes');

        $isOwnProfile = Auth::check() && Auth::id() === $user->id;

        return view('users.profile', compact('user', 'isOwnProfile'));
    }

    final public function getUserPosts(Request $request, string $username): JsonResponse|View
    {
        $user = User::where('username', $username)->firstOrFail();

        $posts = $user->posts()
            ->withPostData()
            ->latest()
            ->paginate(10, ['*'], 'posts_page');

        $this->attachUserVoteStatus($posts);

        if ($request->ajax()) {
            // Option 1: Return JSON data
            // return response()->json($posts);

            // Option 2: Return rendered partial Blade view
            $postsHtml = view('users.partials.posts-list', compact('posts'))->render();
            return response()->json(['html' => $postsHtml, 'hasMorePages' => $posts->hasMorePages()]);
        }

        abort(404); // Or redirect to profile: return redirect()->route('profile.show', $username);
    }

    final public function getUserVotedPosts(Request $request, string $username): JsonResponse|View
    {
        $user = User::where('username', $username)->firstOrFail();

        if (!Auth::check() || Auth::id() !== $user->id) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            abort(403, 'You can only view your own voted posts.');
        }

        $votedPostIds = $user->votes()->pluck('post_id');

        $posts = Post::whereIn('id', $votedPostIds)
            ->withPostData()
            ->latest()
            ->paginate(10, ['*'], 'voted_page');

        $this->attachUserVoteStatus($posts);

        if ($request->ajax()) {
            $postsHtml = view('users.partials.posts-list', compact('posts'))->render(); // Reuse the same partial
            return response()->json(['html' => $postsHtml, 'hasMorePages' => $posts->hasMorePages()]);
        }

        abort(404);
    }


    final public function edit(): View
    {
        $user = Auth::user();
        return view('users.edit', compact('user'));
    }

    final public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('users')->ignore($user->id),
            ],
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['first_name', 'last_name', 'username']);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
            }
            $data['profile_picture'] = $request->file('profile_picture')->store('profile_pictures', 'public');
        }

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    final public function showChangePasswordForm(): View
    {
        if (!Auth::user()->password && Auth::user()->google_id) {
            return redirect()->route('profile.edit')->with('info', 'Password change is not available for accounts created via Google login unless a password has been set manually.');
        }
        return view('users.change-password');
    }

    final public function changePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->password) {
            return redirect()->back()->with('error', 'You cannot change password as none is set (likely logged in via social provider). Consider setting one first if needed.');
        }

        $validator = Validator::make($request->all(), [
            // 'current_password' => 'required|string|current_password',
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('The :attribute is incorrect.');
                }
            }],
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('password.change.form')->with('success', 'Password changed successfully.');
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
