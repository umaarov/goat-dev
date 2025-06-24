<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function index(Request $request): View
    {
        $limit = 25;

        // Most votes collected on posts
        $topByPostVotes = DB::table('users')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture', DB::raw('SUM(posts.total_votes) as total_post_votes'))
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('posts.total_votes', '!=', 0)
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
            ->orderByDesc('total_post_votes')
            ->take($limit)
            ->get();

        // Most posts created
        $topByPostCount = User::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->having('posts_count', '>', 0)
            ->take($limit)
            ->get();

        // Most comments created
        $topByCommentCount = User::query()
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->having('comments_count', '>', 0)
            ->take($limit)
            ->get();

        // Most likes collected on comments
        $topByCommentLikes = DB::table('users')
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture', DB::raw('SUM(comments.likes_count) as total_comment_likes'))
            ->join('comments', 'users.id', '=', 'comments.user_id')
            ->where('comments.likes_count', '>', 0)
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.username', 'users.profile_picture')
            ->orderByDesc('total_comment_likes')
            ->take($limit)
            ->get();

        return view('ratings.index', compact(
            'topByPostVotes',
            'topByPostCount',
            'topByCommentCount',
            'topByCommentLikes'
        ));
    }
}
