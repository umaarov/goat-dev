<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PostCardTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('imagewebp') || !function_exists('imagettftext')) {
            $this->markTestSkipped('GD without WebP or FreeType');
        }
        $this->root = sys_get_temp_dir().'/cards-'.uniqid();
        mkdir("{$this->root}/post_images", 0777, true);
        config(['filesystems.disks.public.root' => $this->root]);
        RateLimiter::clear('post-card-render');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root ?? '/nonexistent');
        parent::tearDown();
    }

    private function solid(string $name, int $r, int $g, int $b): string
    {
        $image = imagecreatetruecolor(800, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        imagewebp($image, "{$this->root}/post_images/{$name}.webp");

        return "post_images/{$name}.webp";
    }

    private function question(array $attributes = []): Post
    {
        return Post::factory()->create($attributes + ['user_id' => User::factory()->create()->id]);
    }

    private function card(Post $post, array $query = []): string
    {
        return route('posts.card', ['username' => $post->user->username, 'post' => $post->id] + $query);
    }

    private function png(string $body)
    {
        $image = imagecreatefromstring($body);
        $this->assertNotFalse($image, 'not an image');

        return $image;
    }

    private function colorAt($image, int $x, int $y): array
    {
        $rgb = imagecolorat($image, $x, $y);

        return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
    }

    public function test_the_card_is_a_1200_by_630_jpeg_with_each_option_on_its_own_side(): void
    {
        $post = $this->question(['option_one_image' => $this->solid('red', 230, 20, 20), 'option_two_image' => $this->solid('blue', 20, 20, 230)]);

        $response = $this->get($this->card($post))->assertOk();

        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $image = $this->png($response->getContent());
        $this->assertSame([1200, 630], [imagesx($image), imagesy($image)]);
        $this->assertLessThan(300 * 1024, strlen($response->getContent()), 'must stay under what WhatsApp accepts for previews');

        // the middle band, between the shaded top and bottom, shows the pictures
        [$r1, , $b1] = $this->colorAt($image, 150, 330);
        [$r2, , $b2] = $this->colorAt($image, 1050, 330);
        $this->assertGreaterThan($b1 + 100, $r1, 'option one (red) belongs on the left');
        $this->assertGreaterThan($r2 + 100, $b2, 'option two (blue) belongs on the right');
    }

    public function test_it_still_works_when_pictures_are_missing_or_not_images(): void
    {
        file_put_contents("{$this->root}/post_images/fake.webp", 'not an image at all');
        $post = $this->question(['option_one_image' => 'post_images/fake.webp', 'option_two_image' => 'post_images/gone.webp']);

        $image = $this->png($this->get($this->card($post))->assertOk()->getContent());

        $this->assertSame(1200, imagesx($image));
    }

    public function test_pictures_outside_the_uploads_directory_are_never_read(): void
    {
        file_put_contents(sys_get_temp_dir().'/outside-card.png', 'x');
        $post = $this->question(['option_one_image' => '../outside-card.png', 'option_two_image' => '/etc/passwd']);

        $this->get($this->card($post))->assertOk();
    }

    public function test_hostile_text_cannot_break_the_card(): void
    {
        $post = $this->question([
            'question' => "<script>alert(1)</script> \0 ".str_repeat('Juda uzun savol ', 8).'😀 Привет oʻzbek gʻoya',
            'option_one_title' => str_repeat('W', 120),
            'option_two_title' => "'; DROP TABLE posts;--",
        ]);

        $image = $this->png($this->get($this->card($post))->assertOk()->getContent());

        $this->assertSame(630, imagesy($image));
    }

    public function test_the_card_shows_the_current_split_and_gets_a_new_version_when_votes_change(): void
    {
        $post = $this->question(['option_one_votes' => 0, 'option_two_votes' => 0, 'total_votes' => 0]);
        $service = app(\App\Services\PostCardImage::class);
        $before = $service->version($post);
        $first = $this->get($this->card($post))->getContent();

        $post->update(['option_one_votes' => 3, 'option_two_votes' => 1, 'total_votes' => 4]);

        $this->assertNotSame($before, $service->version($post->fresh()));
        $this->assertNotSame($first, $this->get($this->card($post))->getContent(), 'new votes, new picture');
    }

    public function test_a_card_is_made_once_and_old_versions_are_cleaned_up(): void
    {
        $post = $this->question();
        $this->get($this->card($post))->assertOk();
        $file = glob("{$this->root}/cards/{$post->id}-*.jpg");
        $this->assertCount(1, $file);
        $mtime = filemtime($file[0]);
        sleep(1);

        $this->get($this->card($post))->assertOk();
        clearstatcache();
        $this->assertSame($mtime, filemtime($file[0]), 'the second request is served from the stored file');

        $post->update(['total_votes' => 9, 'option_one_votes' => 9]);
        $this->get($this->card($post))->assertOk();
        $this->assertCount(1, glob("{$this->root}/cards/{$post->id}-*.jpg"), 'the outdated card was removed');
    }

    public function test_the_card_lives_under_the_question_address_and_a_wrong_username_is_redirected(): void
    {
        $post = $this->question();
        $name = $post->user->username;

        $this->assertSame("/@{$name}/post/{$post->id}/card.jpg", parse_url($this->card($post), PHP_URL_PATH));
        $this->get("/@someone-else/post/{$post->id}/card.jpg?v=abc")->assertRedirect("/@{$name}/post/{$post->id}/card.jpg?v=abc")->assertStatus(301);
        $this->get("/p/{$post->id}/card.jpg")->assertStatus(301)->assertRedirect("/@{$name}/post/{$post->id}"); // the old short path now lands on the question, like any /p/ID/... link
    }

    public function test_unknown_and_deleted_questions_are_404(): void
    {
        $this->get('/@nobody/post/999999/card.jpg')->assertNotFound();

        $post = $this->question();
        $url = $this->card($post);
        $post->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_the_question_page_points_link_previews_at_the_card(): void
    {
        $post = $this->question();

        $html = $this->get(route('posts.show.user-scoped', ['username' => $post->user->username, 'post' => $post->id]))->assertOk()->getContent();

        $card = $this->card($post, ['v' => app(\App\Services\PostCardImage::class)->version($post)]);
        $this->assertStringContainsString('<meta property="og:image" content="'.htmlspecialchars($card, ENT_QUOTES).'">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="'.htmlspecialchars($card, ENT_QUOTES).'">', $html);
    }

    public function test_rendering_is_capped_so_it_cannot_be_used_to_burn_cpu(): void
    {
        $post = $this->question();
        for ($i = 0; $i < 600; $i++) {
            RateLimiter::hit('post-card-render', 60);
        }

        $this->get($this->card($post))->assertStatus(503);
    }
}
