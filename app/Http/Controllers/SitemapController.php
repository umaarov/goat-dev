<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $posts = Post::latest()->get();
        $users = User::all();

        $sitemap = view('sitemap.index', [
            'posts' => $posts,
            'users' => $users,
        ])->render();

        return response($sitemap)->header('Content-Type', 'text/xml');
    }
}
