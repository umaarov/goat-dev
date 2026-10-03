<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Artisan::call('responsecache:clear');
    }

    private function question(array $attributes = []): Post
    {
        return Post::factory()->create($attributes + ['user_id' => User::factory()->create()->id]);
    }

    private function tags(string $html, string $pattern): array
    {
        preg_match_all($pattern, $html, $m);

        return array_map(fn ($v) => html_entity_decode($v), $m[1] ?? []);
    }

    private function crawl(string $path, array $headers = []): string
    {
        // per request: withHeaders() would stay on for every later request of the test
        return $this->get($path, $headers)->assertOk()->getContent();
    }

    public function test_the_language_in_the_url_decides_the_language_not_the_browser_header(): void
    {
        $html = $this->crawl('/?lang=uz', ['Accept-Language' => 'ru']);

        $this->assertStringContainsString('<html lang="uz"', $html);

        // a crawler carries no cookie and no header
        $this->flushSession();
        $this->assertStringContainsString('<html lang="en"', $this->crawl('/'));

        // a person's browser language still applies to the plain URL
        $this->flushSession();
        $this->assertStringContainsString('<html lang="ru"', $this->crawl('/', ['Accept-Language' => 'ru']));
    }

    // the home page and the static pages are cached; a Russian visitor must not fill the cache for everyone else
    public function test_the_page_cache_keeps_languages_apart(): void
    {
        foreach (['/', '/about'] as $path) {
            $this->flushSession();
            $this->assertStringContainsString('<html lang="ru"', $this->crawl($path, ['Accept-Language' => 'ru']), $path);

            $this->flushSession();
            $this->assertStringContainsString('<html lang="en"', $this->crawl($path), "{$path}: served from a cache filled by another language");
        }
    }

    public function test_every_language_page_names_itself_as_canonical_and_the_default_stays_plain(): void
    {
        $base = url('/');

        $this->assertSame([$base], $this->tags($this->crawl('/'), '/<link rel="canonical" href="([^"]+)"/'));
        $this->flushSession();
        $this->assertSame([$base.'?lang=ru'], $this->tags($this->crawl('/?lang=ru'), '/<link rel="canonical" href="([^"]+)"/'));
        $this->assertSame([$base], $this->tags($this->crawl('/?lang=en'), '/<link rel="canonical" href="([^"]+)"/'), 'the default language has one address');
        $this->assertSame([$base], $this->tags($this->crawl('/?lang=es'), '/<link rel="canonical" href="([^"]+)"/'), 'interface-only languages are not indexed separately');
        $this->assertSame([$base.'?lang=ru'], $this->tags($this->crawl('/?lang=ru'), '/<meta property="og:url" content="([^"]+)"/'));
    }

    public function test_hreflang_lists_the_searchable_languages_with_working_addresses(): void
    {
        $post = $this->question();
        $path = "/@{$post->user->username}/post/{$post->id}";
        $base = url($path);

        foreach ([$path, $path.'?lang=ru'] as $page) {
            $this->flushSession();
            $html = $this->crawl($page);
            $found = [];
            preg_match_all('/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)"/', $html, $m, PREG_SET_ORDER);
            foreach ($m as [, $lang, $href]) {
                $found[$lang] = $href;
            }

            $this->assertSame(['en' => $base, 'uz' => $base.'?lang=uz', 'ru' => $base.'?lang=ru', 'x-default' => $base], $found, $page);
            $this->assertStringNotContainsString('pt_BR', implode('', array_keys($found)));
        }
    }

    public function test_every_hreflang_address_really_serves_its_language(): void
    {
        $this->assertStringContainsString('<html lang="ru"', $this->crawl('/?lang=ru'));
        $this->assertStringContainsString('<html lang="uz"', $this->crawl('/?lang=uz'));
        $this->assertStringContainsString('<html lang="en"', $this->crawl('/?lang=en'));
    }

    public function test_pages_that_must_not_be_indexed_carry_no_hreflang(): void
    {
        $this->assertStringNotContainsString('hreflang', $this->crawl('/search?q=x'));
    }

    public function test_og_locale_matches_the_page_language(): void
    {
        $html = $this->crawl('/?lang=ru');

        $this->assertSame(['ru_RU'], $this->tags($html, '/<meta property="og:locale" content="([^"]+)"/'));
        $this->assertEqualsCanonicalizing(['en_US', 'uz_UZ'], $this->tags($html, '/<meta property="og:locale:alternate" content="([^"]+)"/'));
    }

    public function test_the_page_declares_that_it_varies_by_language_and_cookie(): void
    {
        $vary = $this->get('/')->headers->get('Vary');

        $this->assertStringContainsString('Accept-Language', $vary);
        $this->assertStringContainsString('Cookie', $vary);
    }

    public function test_later_home_pages_are_their_own_canonical_page(): void
    {
        $author = User::factory()->create();
        Post::factory()->count(20)->create(['user_id' => $author->id]);

        $this->assertSame([url('/').'?page=2'], $this->tags($this->crawl('/?page=2'), '/<link rel="canonical" href="([^"]+)"/'));
        $this->assertSame([url('/').'?page=2&lang=ru'], $this->tags($this->crawl('/?page=2&lang=ru'), '/<link rel="canonical" href="([^"]+)"/'));
    }

    public function test_the_sitemap_is_built_live_not_read_from_a_stale_file(): void
    {
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));

        $post = $this->question();
        $index = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('text/xml', $index->headers->get('Content-Type'));
        $this->assertStringContainsString('<sitemapindex', $index->getContent());
        foreach (['static', 'posts', 'users'] as $part) {
            $this->assertStringContainsString(route("sitemap.{$part}"), $index->getContent());
        }

        $xml = $this->get(route('sitemap.posts'))->assertOk()->getContent();
        $this->assertStringContainsString(route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]), $xml);
    }

    public function test_the_sitemap_lists_every_language_of_a_page_with_all_alternates(): void
    {
        $post = $this->question();
        $base = route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]);

        $xml = simplexml_load_string($this->get(route('sitemap.posts'))->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');

        $locs = array_map('strval', $xml->xpath('//s:url/s:loc'));
        $this->assertSame([$base, $base.'?lang=uz', $base.'?lang=ru'], $locs);

        foreach ($xml->xpath('//s:url') as $url) {
            $url->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $alternates = [];
            foreach ($url->xpath('x:link') as $link) {
                $alternates[(string) $link['hreflang']] = (string) $link['href'];
            }
            $this->assertSame(['en' => $base, 'uz' => $base.'?lang=uz', 'ru' => $base.'?lang=ru', 'x-default' => $base], $alternates);
        }
    }

    public function test_only_profiles_with_questions_are_in_the_sitemap(): void
    {
        $author = User::factory()->create();
        Post::factory()->create(['user_id' => $author->id]);
        $lurker = User::factory()->create();

        $xml = $this->get(route('sitemap.users'))->assertOk()->getContent();

        $this->assertStringContainsString("/@{$author->username}", $xml);
        $this->assertStringNotContainsString("/@{$lurker->username}", $xml);
    }

    public function test_the_sitemap_is_cached_but_valid_xml_at_every_level(): void
    {
        $this->question();
        foreach (['sitemap.index', 'sitemap.static', 'sitemap.posts', 'sitemap.users'] as $name) {
            $route = $name === 'sitemap.index' ? '/sitemap.xml' : route($name);
            $body = $this->get($route)->assertOk()->getContent();
            $this->assertNotFalse(simplexml_load_string($body), "{$name} is not valid XML");
        }

        $before = $this->get(route('sitemap.posts'))->getContent();
        $this->question();
        $this->assertSame($before, $this->get(route('sitemap.posts'))->getContent(), 'served from the cache');
        Cache::flush();
        $this->assertNotSame($before, $this->get(route('sitemap.posts'))->getContent());
    }

    private function schema(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $graph = [];
        foreach ($m[1] as $blob) {
            $data = json_decode($blob, true);
            $this->assertNotNull($data, 'JSON-LD must be valid JSON');
            foreach ($data['@graph'] ?? [$data] as $item) {
                $graph[$item['@type']] = $item;
            }
        }

        return $graph;
    }

    private function pagePath(Post $post): string
    {
        return "/@{$post->user->username}/post/{$post->id}";
    }

    public function test_a_question_is_marked_up_as_a_qa_page_whose_answers_are_its_options(): void
    {
        $post = $this->question(['question' => 'Tea or coffee?', 'option_one_title' => 'Tea', 'option_two_title' => 'Coffee', 'option_one_votes' => 7, 'option_two_votes' => 3, 'total_votes' => 10]);

        $graph = $this->schema($this->crawl($this->pagePath($post)));

        $this->assertArrayHasKey('QAPage', $graph);
        $question = $graph['QAPage']['mainEntity'];
        $this->assertSame('Question', $question['@type']);
        $this->assertSame('Tea or coffee?', $question['name']);
        $this->assertSame(2, $question['answerCount']);
        $this->assertArrayNotHasKey('acceptedAnswer', $question, 'no poll option is "the" right answer');
        $this->assertSame(['Tea', 'Coffee'], array_column($question['suggestedAnswer'], 'text'));
        $this->assertSame([7, 3], array_column($question['suggestedAnswer'], 'upvoteCount'));
        $this->assertCount(3, $graph['BreadcrumbList']['itemListElement']);

        array_walk_recursive($graph, fn ($value) => $this->assertNotNull($value, 'no null in the markup'));
    }

    public function test_hostile_text_cannot_leave_the_structured_data(): void
    {
        $post = $this->question(['question' => '</script><script>alert(1)</script>', 'option_one_title' => '"><img src=x onerror=alert(2)>']);

        $html = $this->crawl($this->pagePath($post));

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertSame('</script><script>alert(1)</script>', $this->schema($html)['QAPage']['mainEntity']['name']);
    }

    public function test_every_page_has_exactly_one_h1(): void
    {
        $post = $this->question(['question' => 'Cats or dogs?']);

        $this->assertSame(1, substr_count($this->crawl('/'), '<h1'));

        $page = $this->crawl($this->pagePath($post));
        $this->assertSame(1, substr_count($page, '<h1'));
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Cats or dogs\?/', $page);
        $this->assertStringNotContainsString('<h2 class="text-lg text-gray-800 dark:text-gray-100"', $page, 'the question is the h1 here, not an h2');
    }

    public function test_the_title_fits_a_result_page_and_the_description_has_a_useful_fallback(): void
    {
        $long = $this->question(['question' => str_repeat('Which is better for a long summer trip? ', 6), 'option_one_title' => 'Sea', 'option_two_title' => 'Mountains', 'total_votes' => 12, 'ai_generated_context' => null]);

        $html = $this->crawl($this->pagePath($long));

        $title = $this->tags($html, '/<title>([^<]+)<\/title>/')[0];
        $this->assertLessThanOrEqual(60, mb_strlen(trim($title)), 'search results cut titles near 60 characters');
        $this->assertStringEndsWith(' - GOAT.uz', trim($title));
        $description = $this->tags($html, '/<meta name="description" content="([^"]+)"/')[0];
        $this->assertGreaterThan(60, mb_strlen($description));

        $short = $this->question(['question' => 'Tea or coffee?', 'option_one_title' => 'Tea', 'option_two_title' => 'Coffee', 'total_votes' => 5, 'ai_generated_context' => null]);
        $description = $this->tags($this->crawl($this->pagePath($short)), '/<meta name="description" content="([^"]+)"/')[0];
        $this->assertStringContainsString('Tea or Coffee', $description);
        $this->assertStringContainsString('5 votes', $description);

        $summarised = $this->question(['ai_generated_context' => 'A short AI summary of the debate.']);
        $this->assertSame('A short AI summary of the debate.', $this->tags($this->crawl($this->pagePath($summarised)), '/<meta name="description" content="([^"]+)"/')[0]);
    }

    public function test_a_question_links_to_the_authors_other_questions_and_the_most_voted_ones(): void
    {
        $author = User::factory()->create();
        $current = Post::factory()->create(['user_id' => $author->id, 'question' => 'Current one?']);
        $mine = Post::factory()->create(['user_id' => $author->id, 'question' => 'Authors other question?']);
        $star = Post::factory()->create(['question' => 'Most voted of all?', 'total_votes' => 500]);

        $html = $this->crawl($this->pagePath($current));

        $this->assertStringContainsString('href="'.route('posts.show.user-scoped', ['username' => $author->username, 'post' => $mine->id]).'"', $html);
        $this->assertStringContainsString('Most voted of all?', $html);
        $this->assertStringNotContainsString('<a href="'.route('posts.show.user-scoped', ['username' => $author->username, 'post' => $current->id]).'" class="text-blue-600', $html, 'it does not link to itself');
    }

    public function test_old_question_addresses_redirect_for_everyone_and_the_private_ones_stay_private(): void
    {
        $post = $this->question();
        $canonical = $this->pagePath($post);

        $this->get("/posts/{$post->id}")->assertStatus(301)->assertRedirect($canonical);
        $this->get("/p/{$post->id}/some-old-slug")->assertStatus(301)->assertRedirect($canonical);
        $this->get("/p/{$post->id}")->assertStatus(301)->assertRedirect($canonical);
        $this->get('/posts/create')->assertRedirect(route('login'));
        $this->get("/posts/{$post->id}/edit")->assertRedirect(route('login'));
        $this->get('/posts/999999')->assertNotFound();
    }

    public function test_login_and_register_are_noindex_and_crawlable_so_the_noindex_is_seen(): void
    {
        foreach (['/login', '/register'] as $page) {
            $html = $this->crawl($page);
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html, $page);
            $this->assertStringNotContainsString('hreflang', $html, $page);
        }

        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringNotContainsString('Disallow: /login', $robots);
        $this->assertStringNotContainsString('Disallow: /register', $robots);
        $this->assertStringContainsString('Sitemap: https://www.goat.uz/sitemap.xml', $robots);
    }

    public function test_pictures_have_a_real_src_and_only_the_first_one_is_prioritised(): void
    {
        $author = User::factory()->create();
        foreach (range(1, 3) as $i) {
            Post::factory()->create(['user_id' => $author->id, 'option_one_image' => "post_images/a{$i}.webp", 'option_two_image' => "post_images/b{$i}.webp"]);
        }

        $html = $this->crawl('/');

        $this->assertStringNotContainsString('data-src=', $html, 'no JavaScript-only image addresses');
        $this->assertStringNotContainsString('R0lGODlhAQABAAD', $html, 'no placeholder gif');
        preg_match_all('/<img [^>]*post_images\/[^>]*>/', $html, $m);
        $this->assertCount(6, $m[0]);
        $priority = array_filter($m[0], fn ($tag) => str_contains($tag, 'fetchpriority="high"'));
        $this->assertCount(2, $priority, 'the two pictures of the first question');
        $this->assertSame(4, count(array_filter($m[0], fn ($tag) => str_contains($tag, 'loading="lazy"'))));
    }

    public function test_the_question_list_is_visible_without_waiting_for_javascript(): void
    {
        $this->question();

        $html = $this->crawl('/');

        $this->assertStringContainsString('<div id="posts-container">', $html);
        $this->assertStringNotContainsString('id="posts-loading-shimmer"', $html);
    }

    public function test_pinch_zoom_is_not_blocked(): void
    {
        $viewport = $this->tags($this->crawl('/'), '/<meta name="viewport"\s+content="([^"]+)"/')[0];

        $this->assertStringNotContainsString('user-scalable', $viewport);
        $this->assertStringNotContainsString('maximum-scale', $viewport);
    }

    public function test_the_indexnow_key_is_served_only_when_one_is_configured(): void
    {
        config(['services.indexnow.key' => null]);
        $this->get('/indexnow-key.txt')->assertNotFound();

        config(['services.indexnow.key' => 'abc123def4567890']);
        $this->get('/indexnow-key.txt')->assertOk()->assertSee('abc123def4567890', false);
    }

    public function test_static_pages_have_one_h1_and_each_its_own_description(): void
    {
        $descriptions = [];
        foreach (['/about', '/terms', '/privacy-policy', '/sponsorship', '/ads', '/contribution', '/copyright'] as $path) {
            $html = $this->crawl($path);
            $this->assertSame(1, substr_count($html, '<h1'), $path);
            $descriptions[$path] = $this->tags($html, '/<meta name="description" content="([^"]+)"/')[0];
        }

        $this->assertCount(count($descriptions), array_unique($descriptions), 'no two pages share a description: '.json_encode($descriptions));
    }

    public function test_static_pages_are_described_in_the_visitors_language(): void
    {
        $this->flushSession();
        $ru = $this->tags($this->crawl('/about?lang=ru'), '/<meta name="description" content="([^"]+)"/')[0];

        $this->assertStringContainsString('платформа для дебатов', $ru);
    }

    // the list is visible before Alpine runs, so the AI panel must already look the way Alpine will leave it
    public function test_the_ai_panel_is_rendered_in_its_final_state_so_alpine_moves_nothing(): void
    {
        $post = $this->question(['ai_generated_context' => 'Context of the debate.']);
        $page = $this->pagePath($post);

        $guest = $this->crawl($page);
        $this->assertStringContainsString('max-h-24 overflow-hidden', $guest);
        $this->assertStringContainsString('>Show more</span>', $guest);
        $this->assertStringNotContainsString('x-show="isPanelVisible" x-transition class="text-sm font-normal text-left mt-4" style="display: none;"', $guest);

        $hidden = User::factory()->create(['ai_insight_preference' => 'hidden']);
        $this->actingAs($hidden);
        $this->assertStringContainsString('class="text-sm font-normal text-left mt-4" style="display: none;"', $this->crawl($page));

        $expanded = User::factory()->create(['ai_insight_preference' => 'expanded']);
        $this->actingAs($expanded);
        $html = $this->crawl($page);
        $this->assertStringContainsString('max-h-screen', $html);
        $this->assertStringContainsString('>Show less</span>', $html);
    }
}
