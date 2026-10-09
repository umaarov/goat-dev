<?php

namespace Tests\Feature;

use App\Jobs\PromotePostToSocialMedia;
use App\Mail\QuestionMilestone;
use App\Mail\WinBack;
use App\Models\Comment;
use App\Models\Post;
use App\Models\SocialPromotion;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\InstagramService;
use App\Services\Promotion\Promoter;
use App\Services\TelegramService;
use App\Services\XService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['promotion.enabled' => true, 'promotion.daily_cap' => 2]);
    }

    private function question(int $votes = 10, array $attributes = []): Post
    {
        return Post::factory()->create($attributes + [
            'total_votes' => $votes,
            'option_one_votes' => (int) round($votes * 0.6),
            'option_two_votes' => $votes - (int) round($votes * 0.6),
        ]);
    }

    private function recentVote(Post $post): void
    {
        DB::table('votes')->insert([
            'user_id' => User::factory()->create()->id,
            'post_id' => $post->id,
            'vote_option' => 'option_one',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
    }

    public function test_the_question_of_the_day_is_the_best_qualifying_question(): void
    {
        $this->question(3);
        $this->question(40, ['option_one_image' => null]);
        $quiet = $this->question(20);
        $best = $this->question(30);
        Comment::factory()->count(5)->create(['post_id' => $quiet->id]);

        $this->assertSame($quiet->id, app(Promoter::class)->dailyCandidate()->id, '20 votes + 5 comments beat 30 votes');

        app(Promoter::class)->record($quiet, Promoter::DAILY, 0, false);
        $this->assertSame($best->id, app(Promoter::class)->dailyCandidate()->id);
    }

    public function test_a_question_comes_back_after_the_cooldown(): void
    {
        $post = $this->question(30);
        SocialPromotion::create(['post_id' => $post->id, 'kind' => 'daily', 'milestone' => 0, 'networks' => ['telegram']]);

        $this->assertNull(app(Promoter::class)->dailyCandidate());

        SocialPromotion::query()->update(['created_at' => now()->subDays(46)]);
        $this->assertSame($post->id, app(Promoter::class)->dailyCandidate()->id);
    }

    public function test_milestones_need_current_voting_and_are_announced_once(): void
    {
        $old = $this->question(120);
        $active = $this->question(120);
        $this->recentVote($active);
        $this->recentVote($this->question(30));

        $found = app(Promoter::class)->milestoneCandidates();
        $this->assertCount(1, $found);
        [$post, $milestone] = $found->first();
        $this->assertSame($active->id, $post->id);
        $this->assertSame(100, $milestone, 'the highest milestone crossed');
        $this->assertNotSame($old->id, $post->id);

        app(Promoter::class)->record($post, Promoter::MILESTONE, 100, false);
        $this->assertCount(0, app(Promoter::class)->milestoneCandidates());

        $post->update(['total_votes' => 260]);
        $this->assertSame(250, app(Promoter::class)->milestoneCandidates()->first()[1]);
    }

    public function test_captions_carry_the_results_and_tracking_per_network(): void
    {
        $post = $this->question(100, ['question' => 'Pepsi or Cola?', 'option_one_title' => 'Pepsi', 'option_two_title' => 'Cola']);
        $promoter = app(Promoter::class);

        $telegram = $promoter->caption($post, 'telegram', Promoter::DAILY);
        $this->assertStringContainsString('Question of the day', $telegram);
        $this->assertStringContainsString('Pepsi — 60%', $telegram);
        $this->assertStringContainsString('Cola — 40%', $telegram);
        $this->assertStringContainsString('utm_source=telegram&utm_medium=social&utm_campaign=daily', $telegram);

        $instagram = $promoter->caption($post, 'instagram', Promoter::DAILY);
        $this->assertStringNotContainsString('http', $instagram);
        $this->assertStringContainsString('link in bio', $instagram);
        $this->assertStringContainsString('#goatuz', $instagram);

        $this->assertStringContainsString('100 votes are in!', $promoter->caption($post, 'telegram', Promoter::MILESTONE, 100));

        $post->forceFill(['created_at' => now()->subDays(60)])->save();
        $this->assertStringContainsString('Still dividing the crowd', $promoter->caption($post, 'telegram', Promoter::DAILY));
    }

    public function test_the_x_caption_always_fits_in_a_tweet(): void
    {
        $post = $this->question(1234567, [
            'question' => str_repeat('A very long question? ', 11),
            'option_one_title' => str_repeat('One', 15),
            'option_two_title' => str_repeat('Two', 15),
        ]);

        $text = app(Promoter::class)->caption($post, 'x', Promoter::DAILY);
        $withoutLink = preg_replace('~https?://\S+~', str_repeat('x', 23), $text);

        $this->assertLessThanOrEqual(280, mb_strlen($withoutLink));
        $this->assertStringContainsString('utm_source=x', $text);
    }

    public function test_the_daily_command_queues_one_post_and_respects_the_cap(): void
    {
        Queue::fake();
        config(['promotion.daily_cap' => 1]);
        $this->question(30);
        $this->question(20);

        $this->artisan('promote:run daily')->assertSuccessful();
        $this->artisan('promote:run daily')->expectsOutput('Daily cap reached.')->assertSuccessful();

        Queue::assertPushed(PromotePostToSocialMedia::class, 1);
        $this->assertSame(1, SocialPromotion::count());
    }

    public function test_dry_run_and_the_off_switch_post_nothing(): void
    {
        Queue::fake();
        $this->question(30);

        $this->artisan('promote:run daily --dry-run')->expectsOutputToContain('Question of the day')->assertSuccessful();
        config(['promotion.enabled' => false]);
        $this->artisan('promote:run daily')->expectsOutput('Promotion is off (PROMOTION_ENABLED).')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(0, SocialPromotion::count());
    }

    public function test_the_job_posts_the_caption_to_every_network_and_survives_one_failing(): void
    {
        Storage::fake('public');
        $post = $this->question(30);
        Storage::disk('public')->put($post->option_one_image, 'x');
        Storage::disk('public')->put($post->option_two_image, 'x');
        $promotion = app(Promoter::class)->record($post, Promoter::DAILY, 0, false);
        $promotion->update(['networks' => []]);

        $this->mock(TelegramService::class, fn ($m) => $m->shouldReceive('share')->once()->withArgs(fn ($p, $caption) => $p->is($post) && str_contains($caption, 'utm_source=telegram')));
        $this->mock(InstagramService::class, fn ($m) => $m->shouldReceive('share')->once()->andThrow(new \RuntimeException('instagram down')));
        $this->mock(XService::class, fn ($m) => $m->shouldReceive('share')->once());

        (new PromotePostToSocialMedia($promotion->id))->handle(app(Promoter::class), app(TelegramService::class));

        $this->assertSame(['telegram', 'x'], $promotion->fresh()->networks);
    }

    public function test_a_retry_skips_networks_that_already_went_out(): void
    {
        Storage::fake('public');
        $post = $this->question(30);
        Storage::disk('public')->put($post->option_one_image, 'x');
        Storage::disk('public')->put($post->option_two_image, 'x');
        $promotion = SocialPromotion::create(['post_id' => $post->id, 'kind' => 'daily', 'milestone' => 0, 'networks' => ['telegram', 'x']]);

        $this->mock(TelegramService::class, fn ($m) => $m->shouldReceive('share')->never());
        $this->mock(XService::class, fn ($m) => $m->shouldReceive('share')->never());
        $this->mock(InstagramService::class, fn ($m) => $m->shouldReceive('share')->once());

        (new PromotePostToSocialMedia($promotion->id))->handle(app(Promoter::class), app(TelegramService::class));

        $this->assertSame(['telegram', 'x', 'instagram'], $promotion->fresh()->networks);
    }

    public function test_a_milestone_is_posted_and_the_author_is_told_once(): void
    {
        Queue::fake();
        Mail::fake();
        $author = User::factory()->create(['receives_notifications' => true]);
        $post = $this->question(120, ['user_id' => $author->id]);
        $this->recentVote($post);

        $this->artisan('promote:run milestone')->assertSuccessful();
        $this->artisan('promote:run milestone')->assertSuccessful();

        Queue::assertPushed(PromotePostToSocialMedia::class, 1);
        Mail::assertQueued(QuestionMilestone::class, 1);
        Mail::assertQueued(QuestionMilestone::class, fn ($mail) => $mail->hasTo($author->email) && $mail->milestone === 100);
    }

    public function test_an_unsubscribed_author_gets_no_email_and_a_full_day_posts_no_social(): void
    {
        Queue::fake();
        Mail::fake();
        config(['promotion.daily_cap' => 0]);
        $author = User::factory()->create(['receives_notifications' => false]);
        $post = $this->question(120, ['user_id' => $author->id]);
        $this->recentVote($post);

        $this->artisan('promote:run milestone')->assertSuccessful();

        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
        $this->assertNull(SocialPromotion::first()->networks, 'recorded so it is not announced again, but not posted');
    }

    public function test_the_milestone_email_has_headers_and_renders_in_every_language(): void
    {
        $author = User::factory()->create();
        $post = $this->question(100, ['user_id' => $author->id]);
        $posts = collect([$post]);

        foreach (array_keys(config('app.available_locales')) as $locale) {
            app()->setLocale($locale);
            foreach ([new QuestionMilestone($author, $post, 100), new WinBack($author, $posts)] as $mail) {
                $html = $mail->render();
                $this->assertStringNotContainsString('mail.milestone.', $html, $locale);
                $this->assertStringNotContainsString('mail.winback.', $html, $locale);
                $this->assertStringNotContainsString('mail.footer.', $html, $locale);
                $text = view($mail->content()->text, $mail->content()->with)->render();
                $this->assertStringNotContainsString('&amp;', $text, $locale);
                $this->assertStringContainsString('utm_campaign=', $html);
                $this->assertArrayHasKey('List-Unsubscribe-Post', $mail->headers()->text);
            }
        }
    }

    private function lapsed(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'receives_notifications' => true,
            'email_verified_at' => now()->subYear(),
            'last_active_at' => now()->subDays(60),
        ]);
    }

    public function test_win_back_is_off_by_default(): void
    {
        Mail::fake();
        $this->question(30);
        $this->lapsed();

        $this->artisan('mail:win-back')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_win_back_reaches_only_the_right_people_in_small_numbers_and_never_twice(): void
    {
        Mail::fake();
        config(['promotion.winback.enabled' => true, 'promotion.winback.per_day' => 2]);
        $this->question(30);
        $warm = $this->lapsed(['last_active_at' => now()->subDays(35)]);
        $cooler = $this->lapsed(['last_active_at' => now()->subDays(90)]);
        $this->lapsed(['last_active_at' => now()->subDays(150)]);
        $this->lapsed(['last_active_at' => now()->subDays(5)]);
        $this->lapsed(['last_active_at' => now()->subDays(400)]);
        $this->lapsed(['receives_notifications' => false]);
        $this->lapsed(['email_verified_at' => null]);

        $this->artisan('mail:win-back')->assertSuccessful();

        Mail::assertQueued(WinBack::class, 2);
        Mail::assertQueued(WinBack::class, fn ($m) => $m->hasTo($warm->email));
        Mail::assertQueued(WinBack::class, fn ($m) => $m->hasTo($cooler->email));
        $this->assertNotNull($warm->fresh()->winback_sent_at);

        $this->artisan('mail:win-back')->assertSuccessful();
        Mail::assertQueued(WinBack::class, 3);
        $this->assertSame(3, User::whereNotNull('winback_sent_at')->count());

        $this->artisan('mail:win-back')->assertSuccessful();
        Mail::assertQueued(WinBack::class, 3);
    }

    public function test_replies_go_to_the_configured_inbox(): void
    {
        config(['mail.reply_to.address' => 'help@example.test', 'mail.reply_to.name' => 'GOAT help', 'mail.default' => 'array']);
        app()->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        (new AppServiceProvider($this->app))->boot();

        Mail::raw('hello', fn ($m) => $m->to('someone@example.test')->subject('x'));

        $message = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('help@example.test', $message->getReplyTo()[0]->getAddress());
    }
}
