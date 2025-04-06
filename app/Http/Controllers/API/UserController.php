<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'profile_picture' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
        ];

        if ($request->hasFile('profile_picture')) {
            // Delete old profile picture if exists
            if ($user->profile_picture && !str_starts_with($user->profile_picture, 'http')) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            $data['profile_picture'] = $request->file('profile_picture')->store('profile_pictures', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Check if user has a password (might not if using social login only)
        if (!$user->password) {
            return response()->json(['message' => 'You do not have a password set up. Please set up a password first.'], 400);
        }

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 401);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function getProfile($username)
    {
        $user = User::where('username', $username)
            ->select(['id', 'first_name', 'last_name', 'username', 'profile_picture', 'created_at'])
            ->firstOrFail();

        // Get stats
        $user->posts_count = $user->posts()->count();
        $user->votes_received = $user->posts()->sum('total_votes');

        return response()->json($user);
    }

    public function getUserPosts($username, Request $request)
    {
        $user = User::where('username', $username)->firstOrFail();

        $posts = $user->posts()
            ->with(['user:id,username,profile_picture'])
            ->withCount(['comments', 'shares'])
            ->latest()
            ->paginate(15);

        // Add user vote information if authenticated
        if ($request->user()) {
            $posts->getCollection()->transform(function ($post) use ($request) {
                $vote = $post->votes()->where('user_id', $request->user()->id)->first();
                $post->user_vote = $vote ? $vote->vote_option : null;
                return $post;
            });
        }

        return response()->json($posts);
    }
}
