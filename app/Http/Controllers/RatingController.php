<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function index(Request $request): View
    {
        $limit = 25;

        // Most votes collected on posts
        $topByPostVotes = User::query()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.username',
                'users.profile_picture',
                DB::raw('SUM(posts.total_votes) as total_post_votes')
            ])
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->groupBy(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.username',
                'users.profile_picture'
            )
            ->orderByDesc('total_post_votes')
            ->take($limit)
            ->get();

        // Most posts created
        $topByPostCount = User::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->take($limit)
            ->get();

        // Most comments created
        $topByCommentCount = User::query()
            ->withCount('comments')
            ->orderByDesc('comments_count')
            ->take($limit)
            ->get();

        // Most likes collected on comments
        $topByCommentLikes = User::query()
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.username',
                'users.profile_picture',
                DB::raw('SUM(comments.likes_count) as total_comment_likes')
            ])
            ->join('comments', 'users.id', '=', 'comments.user_id')
            ->where('comments.likes_count', '>', 0)
            ->groupBy(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.username',
                'users.profile_picture'
            )
            ->orderByDesc('total_comment_likes')
            ->take($limit)
            ->get();


        return view('rating.index', compact(
            'topByPostVotes',
            'topByPostCount',
            'topByCommentCount',
            'topByCommentLikes'
        ));
    }
}
