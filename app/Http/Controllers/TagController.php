<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagController extends Controller
{
    // a topic with fewer questions than this is a thin page: reachable, but kept out of search and the sitemap
    public static function minQuestions(): int
    {
        return (int) config('seo.tag_min_questions', 2);
    }

    final public function index(): View
    {
        $tags = Tag::query()
            ->withCount('posts')
            ->having('posts_count', '>=', self::minQuestions())
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get();

        return view('tags.index', compact('tags'));
    }

    final public function show(Request $request, string $slug): View
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();

        $posts = $tag->posts()
            ->withPostData()
            ->orderByDesc('total_votes')
            ->orderByDesc('posts.id')
            ->paginate(15);

        abort_if($posts->total() === 0, 404);

        $this->attachUserVotes($posts);

        return view('tags.show', [
            'tag' => $tag,
            'posts' => $posts,
            'indexable' => $posts->total() >= self::minQuestions() && $posts->currentPage() === 1,
        ]);
    }

    private function attachUserVotes(LengthAwarePaginator $posts): void
    {
        $votes = Auth::check()
            ? Vote::where('user_id', Auth::id())->whereIn('post_id', $posts->pluck('id'))->pluck('vote_option', 'post_id')
            : collect();

        $posts->getCollection()->transform(function (Post $post) use ($votes) {
            $post->user_vote = $votes->get($post->id);

            return $post;
        });
    }
}
