<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $latestPost = Post::latest('updated_at')->first();
        $latestUser = User::latest('updated_at')->first();

        return response()
            ->view('sitemap.index', [
                'latestPost' => $latestPost,
                'latestUser' => $latestUser,
            ])
            ->header('Content-Type', 'text/xml');
    }

    public function static(): Response
    {
        return response()
            ->view('sitemap.static')
            ->header('Content-Type', 'text/xml');
    }

    public function posts(): Response
    {
        $posts = Post::with('user')->latest('updated_at')->get();

        return response()
            ->view('sitemap.posts', ['posts' => $posts])
            ->header('Content-Type', 'text/xml');
    }

    public function users(): Response
    {
        $users = User::latest('updated_at')->get();
        return response()
            ->view('sitemap.users', ['users' => $users])
            ->header('Content-Type', 'text/xml');
    }
}
