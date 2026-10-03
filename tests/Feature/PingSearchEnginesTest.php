<?php

namespace Tests\Feature;

use App\Jobs\PingSearchEngines;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PingSearchEnginesTest extends TestCase
{
    use RefreshDatabase;

    private function production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['services.indexnow.key' => 'abc123def4567890', 'services.indexnow.endpoint' => 'https://indexnow.test/indexnow']);
    }

    private function question(): Post
    {
        return Post::factory()->create(['user_id' => User::factory()->create()->id]);
    }

    public function test_it_names_every_language_address_of_the_question_and_the_home_page(): void
    {
        $post = $this->question();
        $base = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);

        $this->assertSame([$base, $base.'?lang=uz', $base.'?lang=ru', url('/')], PingSearchEngines::urlsFor($post));
    }

    public function test_it_submits_to_indexnow_in_production(): void
    {
        $this->production();
        Http::fake(['indexnow.test/*' => Http::response('', 200)]);
        $post = $this->question();

        (new PingSearchEngines(PingSearchEngines::urlsFor($post)))->handle();

        Http::assertSent(function (Request $request) use ($post) {
            return $request->url() === 'https://indexnow.test/indexnow'
                && $request['key'] === 'abc123def4567890'
                && $request['keyLocation'] === route('indexnow.key')
                && $request['host'] === parse_url(url('/'), PHP_URL_HOST)
                && $request['urlList'] === PingSearchEngines::urlsFor($post);
        });
    }

    public function test_it_sends_nothing_outside_production_without_a_key_or_without_urls(): void
    {
        Http::fake();
        $urls = ['https://www.goat.uz/x'];

        config(['services.indexnow.key' => 'abc123def4567890']);
        (new PingSearchEngines($urls))->handle(); // testing environment

        $this->production();
        config(['services.indexnow.key' => null]);
        (new PingSearchEngines($urls))->handle();

        config(['services.indexnow.key' => 'abc123def4567890']);
        (new PingSearchEngines([]))->handle();

        Http::assertNothingSent();
    }

    public function test_only_addresses_of_one_host_are_submitted(): void
    {
        $this->production();
        Http::fake(['indexnow.test/*' => Http::response('', 200)]);

        (new PingSearchEngines(['https://www.goat.uz/a', 'https://www.goat.uz/b', 'https://elsewhere.example/c']))->handle();

        Http::assertSent(fn (Request $r) => $r['urlList'] === ['https://www.goat.uz/a', 'https://www.goat.uz/b'] && $r['host'] === 'www.goat.uz');
    }

    public function test_a_rejection_is_logged_but_a_server_error_or_rate_limit_is_retried(): void
    {
        $this->production();
        Log::spy();

        Http::fake(['indexnow.test/*' => Http::response('', 422)]);
        (new PingSearchEngines(['https://www.goat.uz/a']))->handle();
        Log::shouldHaveReceived('warning')->once();

        foreach ([500, 429] as $status) {
            Http::fake(['indexnow.test/*' => Http::response('', $status)]);
            try {
                (new PingSearchEngines(['https://www.goat.uz/a']))->handle();
                $this->fail("{$status} must make the job retry");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString((string) $status, $e->getMessage());
            }
        }
    }

    public function test_the_controllers_pass_the_changed_addresses(): void
    {
        foreach (['app/Http/Controllers/PostController.php', 'app/Http/Controllers/Api/V1/PostController.php'] as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringNotContainsString('PingSearchEngines::dispatch();', $source, $file);
            $this->assertStringContainsString('PingSearchEngines::dispatch(PingSearchEngines::urlsFor($post))', $source, $file);
        }
    }
}
