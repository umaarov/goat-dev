<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

// built on request (a few queries, cached ten minutes), so a new question shows up within minutes, not at 02:00
class SitemapController extends Controller
{
    private const TTL = 600;

    private const PAGES = [
        ['path' => '/about', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => '/terms', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['path' => '/copyright', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['path' => '/sponsorship', 'changefreq' => 'monthly', 'priority' => '0.4'],
        ['path' => '/ads', 'changefreq' => 'monthly', 'priority' => '0.4'],
        ['path' => '/contribution', 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];

    public function index(): Response
    {
        return $this->xml('sitemap.index', fn () => view('sitemap.index', [
            'latestPost' => Post::latest('updated_at')->first(),
            'latestUser' => User::whereHas('posts')->latest('updated_at')->first(),
            'hasTags' => $this->topics()->isNotEmpty(),
        ])->render());
    }

    public function static(): Response
    {
        return $this->xml('sitemap.static', fn () => view('sitemap.static', [
            'pages' => self::PAGES,
            'homeLastmod' => (Post::latest('updated_at')->value('updated_at') ?? now())->tz('Asia/Tashkent')->toAtomString(),
        ])->render());
    }

    public function posts(): Response
    {
        return $this->xml('sitemap.posts', fn () => view('sitemap.posts', [
            'posts' => Post::with('user')->latest('updated_at')->get(),
        ])->render());
    }

    // profiles with at least one question: an empty profile is a thin page
    public function users(): Response
    {
        return $this->xml('sitemap.users', fn () => view('sitemap.users', [
            'users' => User::whereHas('posts')->latest('updated_at')->get(),
        ])->render());
    }

    // topics with enough questions to be more than a thin page
    private function topics()
    {
        return Tag::query()
            ->withCount('posts')
            ->withMax('posts', 'updated_at')
            ->having('posts_count', '>=', TagController::minQuestions())
            ->orderBy('slug')
            ->get();
    }

    public function tags(): Response
    {
        return $this->xml('sitemap.tags', fn () => view('sitemap.tags', ['tags' => $this->topics()])->render());
    }

    private function xml(string $key, \Closure $render): Response
    {
        $body = Cache::remember($key.':'.request()->getSchemeAndHttpHost(), self::TTL, $render);

        return response($body, 200, [
            'Content-Type' => 'text/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}
