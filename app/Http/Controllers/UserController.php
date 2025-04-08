<?php

namespace App\Http\Controllers;
// Changed Namespace

use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    final function edit(): View
    {
        $user = Auth::user();
        return view('users.edit', compact('user')); // Assumes view at resources/views/users/edit.blade.php
    }

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

    final function showChangePasswordForm(): View
    {
        return view('users.change-password');
    }


    final function changePassword(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->password) {
            return redirect()->back()->with('error', 'You cannot change password as none is set (likely logged in via social provider).');
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
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

    final function showProfile(string $username): View
    {
        $user = User::where('username', $username)
            ->select(['id', 'first_name', 'last_name', 'username', 'profile_picture', 'created_at'])
            ->firstOrFail();

        $user->posts_count = $user->posts()->count();
        $user->votes_received = $user->posts()->sum('total_votes');

        return view('users.profile', compact('user'));
    }

    final function showUserPosts(string $username): View
    {
        $user = User::where('username', $username)->firstOrFail();

        $posts = $user->posts()
            ->with([
                'user:id,username,profile_picture',
                // 'voters:id,username,profile_picture'
            ])
            ->withCount(['comments', 'shares'])
            ->latest()
            ->paginate(15);

        if (Auth::check()) {
            $loggedInUserId = Auth::id();
            $postIds = $posts->pluck('id');

            $userVotes = Vote::where('user_id', $loggedInUserId)
                ->whereIn('post_id', $postIds)
                ->pluck('vote_option', 'post_id'); // ['post_id' => 'option_one']

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


        return view('users.posts', compact('user', 'posts')); // Assumes view at resources/views/users/posts.blade.php
    }

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

        $loggedInUserId = $user->id;
        $postIds = $posts->pluck('id');
        $userVotes = Vote::where('user_id', $loggedInUserId)
            ->whereIn('post_id', $postIds)
            ->pluck('vote_option', 'post_id');

        $posts->getCollection()->transform(function ($post) use ($userVotes) {
            $post->user_vote = $userVotes->get($post->id);
            return $post;
        });

        return view('users.my-posts', compact('user', 'posts')); // Assumes view at resources/views/users/my-posts.blade.php
    }
}
