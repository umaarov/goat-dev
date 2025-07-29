<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SitemapController extends Controller
{
    public function index(): StreamedResponse
    {
        $stream = function () {
            echo View::make('sitemap.partials.header')->render();
            echo View::make('sitemap.partials.static')->render();

            Post::with('user')->chunkById(2000, function ($posts) {
                echo View::make('sitemap.partials.posts', ['posts' => $posts])->render();
            });

            User::chunkById(2000, function ($users) {
                echo View::make('sitemap.partials.users', ['users' => $users])->render();
            });

            echo View::make('sitemap.partials.footer')->render();
        };

        return response()->stream($stream, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
