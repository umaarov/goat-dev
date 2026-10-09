<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Support\TagIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagPagesTest extends TestCase
{
    use RefreshDatabase;

    private function question(string $tags, array $attributes = []): Post
    {
        return Post::factory()->create($attributes + ['ai_generated_tags' => $tags, 'total_votes' => 10]);
    }

    public function test_tags_are_normalised_deduplicated_and_junk_is_dropped(): void
    {
        $this->assertSame(
            ['ai-risk' => 'AI risk', 'marvel' => 'Marvel', 'cs2' => 'cs2'],
            TagIndex::parse('AI risk, ai  risk , #Marvel,, ,!!!, cs2'),
        );
        $this->assertSame([], TagIndex::parse(null));
        $this->assertSame(['politika' => 'Политика'], TagIndex::parse('Политика'), 'Cyrillic gets a Latin address');
        $this->assertCount(12, TagIndex::parse(implode(',', range(1, 30))));
    }

    public function test_saving_a_question_indexes_its_tags_and_editing_them_updates_the_index(): void
    {
        $post = $this->question('Marvel, DC, comics');
        $this->assertEqualsCanonicalizing(['dc', 'marvel', 'comics'], $post->tags()->pluck('slug')->all());

        $post->update(['ai_generated_tags' => 'Marvel, movies']);
        $this->assertEqualsCanonicalizing(['marvel', 'movies'], $post->tags()->pluck('slug')->all());
        $this->assertSame(1, Tag::where('slug', 'marvel')->count(), 'one row per tag, shared by questions');

        $post->update(['question' => 'Another question?']);
        $this->assertCount(2, $post->tags);
    }

    public function test_rebuild_indexes_existing_questions(): void
    {
        $post = $this->question('Marvel');
        \DB::table('post_tag')->delete();
        Tag::query()->delete();

        $this->artisan('tags:rebuild')->expectsOutput('Indexed tags of 1 questions.')->assertSuccessful();

        $this->assertSame(['marvel'], $post->tags()->pluck('slug')->all());
    }

    public function test_the_topic_page_lists_the_most_voted_questions_with_seo_markup(): void
    {
        $small = $this->question('Marvel', ['question' => 'Small one?', 'total_votes' => 3]);
        $big = $this->question('Marvel, DC', ['question' => 'Big one?', 'total_votes' => 50]);
        $this->question('Football', ['question' => 'Other topic?']);

        $html = $this->get(route('tags.show', ['slug' => 'marvel']))->assertOk()->getContent();

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('#Marvel', $html);
        $this->assertLessThan(strpos($html, 'Small one?'), strpos($html, 'Big one?'), 'most voted first');
        $this->assertStringNotContainsString('Other topic?', $html);
        $this->assertStringContainsString('<title>Marvel polls &amp; questions - GOAT.uz</title>', $html);
        $this->assertStringContainsString('rel="canonical" href="'.route('tags.show', ['slug' => 'marvel']).'"', $html);
        $this->assertStringContainsString('content="index, follow"', $html);
        $this->assertStringContainsString('"@type":"CollectionPage"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('/@'.$big->user->username.'/post/'.$big->id.'/card.jpg', $html, 'share card of the top question');
        $this->assertStringContainsString('hreflang="uz"', $html);
        $this->assertNotNull($small->id);
    }

    public function test_an_unknown_topic_or_one_with_only_deleted_questions_is_a_404(): void
    {
        $this->get(route('tags.show', ['slug' => 'nothing-here']))->assertNotFound();

        $post = $this->question('Gone');
        $post->delete();
        $this->get(route('tags.show', ['slug' => 'gone']))->assertNotFound();
    }

    public function test_a_topic_with_a_single_question_works_but_stays_out_of_search(): void
    {
        $this->question('Lonely');

        $html = $this->get(route('tags.show', ['slug' => 'lonely']))->assertOk()->getContent();

        $this->assertStringContainsString('content="noindex, follow"', $html);
        $this->assertStringNotContainsString('hreflang="uz"', $html);
    }

    public function test_the_topic_index_and_the_sitemap_only_list_topics_with_enough_questions(): void
    {
        $this->question('Marvel, Lonely');
        $this->question('Marvel');

        $index = $this->get(route('tags.index'))->assertOk()->getContent();
        $this->assertStringContainsString('#Marvel', $index);
        $this->assertStringNotContainsString('#Lonely', $index);

        $sitemap = $this->get('/sitemaps/tags.xml')->assertOk()->getContent();
        $this->assertStringContainsString(route('tags.show', ['slug' => 'marvel']), $sitemap);
        $this->assertStringNotContainsString('lonely', $sitemap);
        $this->assertStringContainsString(route('tags.index'), $sitemap);
        $this->assertStringContainsString('hreflang="ru"', $sitemap);

        $this->get('/sitemap.xml')->assertSee(route('sitemap.tags'), false);
    }

    public function test_the_sitemap_index_skips_the_topics_file_when_there_are_none(): void
    {
        $this->question('Lonely');

        $this->get('/sitemap.xml')->assertDontSee('sitemaps/tags.xml', false);
    }

    public function test_cards_link_tags_to_their_topic_page_and_the_footer_links_the_index(): void
    {
        $this->question('Marvel, Lonely');
        $this->question('Marvel');

        $home = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('tags.show', ['slug' => 'marvel']).'"', $home);
        $this->assertStringContainsString('href="'.route('tags.index').'"', $home);
    }

    public function test_the_topic_pages_speak_the_visitors_language(): void
    {
        $this->question('Marvel');
        $this->question('Marvel');

        $this->get(route('tags.show', ['slug' => 'marvel']).'?lang=ru')->assertOk()->assertSee('Все темы');
        $this->get(route('tags.index').'?lang=uz')->assertOk()->assertSee('Mavzular');
    }

    public function test_the_topic_page_paginates_without_indexing_later_pages(): void
    {
        Post::factory()->count(17)->create(['ai_generated_tags' => 'Marvel', 'total_votes' => 5]);

        $this->get(route('tags.show', ['slug' => 'marvel']))->assertSee('content="index, follow"', false);
        $this->get(route('tags.show', ['slug' => 'marvel']).'?page=2')->assertOk()->assertSee('content="noindex, follow"', false);
    }
}
