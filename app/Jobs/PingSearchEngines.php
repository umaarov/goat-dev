<?php

namespace App\Jobs;

use App\Models\Post;
use App\Support\SeoUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// IndexNow: tells Bing, Yandex, Naver and Seznam which addresses are new, changed or gone.
// (Google retired its sitemap ping; it finds changes through the sitemap's lastmod.)
class PingSearchEngines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    public $timeout = 30;

    public $backoff = [30, 300];

    public function __construct(public array $urls = [])
    {
    }

    // every language address of a question, and the home page that lists it
    public static function urlsFor(Post $post): array
    {
        $post->loadMissing('user:id,username');
        $question = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);

        return array_values(array_merge(array_values(SeoUrls::variants($question)), [url('/')]));
    }

    public function handle(): void
    {
        $key = config('services.indexnow.key');
        $urls = array_values(array_unique(array_filter($this->urls)));

        if (!App::isProduction() || !$key || $urls === []) {
            return;
        }

        $host = parse_url($urls[0], PHP_URL_HOST);
        // the protocol only accepts addresses of one host
        $urls = array_values(array_filter($urls, fn ($url) => parse_url($url, PHP_URL_HOST) === $host));

        $response = Http::timeout(10)->asJson()->post(config('services.indexnow.endpoint'), [
            'host' => $host,
            'key' => $key,
            'keyLocation' => route('indexnow.key'),
            'urlList' => $urls,
        ]);

        if ($response->serverError() || $response->status() === 429) {
            throw new RuntimeException("IndexNow answered {$response->status()}");
        }
        if ($response->failed()) {
            Log::warning('IndexNow rejected the submission', ['status' => $response->status(), 'urls' => count($urls)]);
        }
    }
}
