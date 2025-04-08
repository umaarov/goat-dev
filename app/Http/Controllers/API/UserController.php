<?php

namespace App\Http\Controllers; // Changed Namespace

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Show the user's profile editing form.
     */
    final function edit(): View
    {
        $user = Auth::user();
        return view('users.edit', compact('user')); // Assumes view at resources/views/users/edit.blade.php
    }

    /**
     * Update the user's profile information.
     */
    final function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'profile_picture' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
        ];

        if ($request->hasFile('profile_picture')) {
            // Delete old picture if it exists and isn't a URL (e.g., from social login)
            if ($user->profile_picture && !filter_var($user->profile_picture, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($user->profile_picture)) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
            }
            $data['profile_picture'] = $request->file('profile_picture')->store('profile_pictures', 'public');
        }

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.'); // Redirect back to edit form
    }

    /**
     * Show the form for changing the user's password.
     */
    final function showChangePasswordForm(): View
    {
        return view('users.change-password'); // Assumes view at resources/views/users/change-password.blade.php
    }


    /**
     * Change the user's password.
     */
    final function changePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Check if the user has a password set (might not if logged in via socialite)
        if (!$user->password) {
            return redirect()->back()->with('error', 'You cannot change password as none is set (likely logged in via social provider).');
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed', // Use confirmed rule
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator); // Don't flash passwords
        }


        if (!Hash::check($request->current_password, $user->password)) {
            return redirect()->back()
                ->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('password.change.form')->with('success', 'Password changed successfully.'); // Redirect back to change password form
    }

    /**
     * Display the public profile of a user.
     */
    final function showProfile(string $username): View // Use username from route
    {
        // Find user or fail
        $user = User::where('username', $username)
            ->select(['id', 'first_name', 'last_name', 'username', 'profile_picture', 'created_at'])
            ->firstOrFail(); // Throws 404 if not found

        // Load counts (can be done via accessors/mutators or relations as well)
        $user->posts_count = $user->posts()->count();
        $user->votes_received = $user->posts()->sum('total_votes');

        return view('users.profile', compact('user')); // Assumes view at resources/views/users/profile.blade.php
    }

    /**
     * Display the posts created by a specific user.
     */
    final function showUserPosts(string $username): View // Use username from route
    {
        $user = User::where('username', $username)->firstOrFail();

        $posts = $user->posts()
            ->with([ // Eager load needed relationships
                'user:id,username,profile_picture',
                // Removed voters loading here, might be too much data for a list view
                // 'voters:id,username,profile_picture'
            ])
            ->withCount(['comments', 'shares']) // Eager load counts
            ->latest()
            ->paginate(15); // Paginate results

        // Determine if the logged-in user voted on these posts (if needed in the view)
        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id');

            // Get votes efficiently for the current page of posts
            $userVotes = \App\Models\Vote::where('user_id', $loggedInUserId)
                ->whereIn('post_id', $postIds)
                ->pluck('vote_option', 'post_id'); // ['post_id' => 'option_one']

            $posts->getCollection()->transform(function ($post) use ($userVotes) {
                $post->user_vote = $userVotes->get($post->id); // Assign vote from the collection
                return $post;
            });
        } else {
            $posts->getCollection()->transform(function ($post) {
                $post->user_vote = null; // Ensure property exists even for guests
                return $post;
            });
        }


        return view('users.posts', compact('user', 'posts')); // Assumes view at resources/views/users/posts.blade.php
    }

    /**
     * Display the posts the currently logged-in user has created.
     * (Convenience method for logged-in user's own posts)
     */
    final function showMyPosts(): View
    {
        $user = Auth::user();

        $posts = $user->posts()
            ->with([
                'user:id,username,profile_picture',
            ])
            ->withCount(['comments', 'shares'])
            ->latest()
            ->paginate(15);

        // Add user_vote status for consistency if needed in the view
        $loggedInUserId = $user->id;
        $postIds = $posts->pluck('id');
        $userVotes = \App\Models\Vote::where('user_id', $loggedInUserId)
            ->whereIn('post_id', $postIds)
            ->pluck('vote_option', 'post_id');

        $posts->getCollection()->transform(function ($post) use ($userVotes) {
            $post->user_vote = $userVotes->get($post->id);
            return $post;
        });

        return view('users.my-posts', compact('user', 'posts')); // Assumes view at resources/views/users/my-posts.blade.php
    }
}
