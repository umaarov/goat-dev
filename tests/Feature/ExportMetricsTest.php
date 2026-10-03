<?php

namespace Tests\Feature;

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Carbon;
use App\Models\Comment;
use App\Models\Post;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportMetricsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/goat-metrics-'.uniqid().'/goat.prom';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @rmdir(dirname($this->path));
        parent::tearDown();
    }

    private function export(): string
    {
        $this->artisan('app:export-metrics', ['--path' => $this->path])->assertExitCode(0);

        return file_get_contents($this->path);
    }

    private function value(string $text, string $series): float
    {
        $this->assertMatchesRegularExpression('/^'.preg_quote($series, '/').' (\S+)$/m', $text, "missing {$series}");
        preg_match('/^'.preg_quote($series, '/').' (\S+)$/m', $text, $m);

        return (float) $m[1];
    }

    public function test_it_writes_valid_prometheus_text_with_contiguous_families(): void
    {
        $text = $this->export();

        $this->assertStringEndsWith("\n", $text);
        $types = [];
        foreach (explode("\n", trim($text)) as $line) {
            if (str_starts_with($line, '# TYPE ')) {
                $name = explode(' ', $line)[2];
                $this->assertNotContains($name, $types, "{$name} declared twice");
                $types[] = $name;
            } elseif ($line !== '' && !str_starts_with($line, '#')) {
                $this->assertMatchesRegularExpression('/^[a-zA-Z_:][a-zA-Z0-9_:]*(\{[^}]*\})? -?\d+(\.\d+)?$/', $line);
            }
        }

        foreach (['goat_queue_size', 'goat_failed_jobs', 'goat_users', 'goat_refresh_tokens_active', 'goat_metrics_export_timestamp_seconds'] as $metric) {
            $this->assertContains($metric, $types);
        }
        $this->assertSame(1.0, $this->value($text, 'goat_metrics_collector_success{collector="content"}'));
        $this->assertSame(1.0, $this->value($text, 'goat_metrics_collector_success{collector="queues"}'));
    }

    public function test_counts_reflect_live_content_only(): void
    {
        $author = User::factory()->create();
        User::factory()->create()->delete();
        $post = Post::factory()->create(['user_id' => $author->id]);
        Post::factory()->create(['user_id' => $author->id])->delete();
        Comment::factory()->count(3)->create(['post_id' => $post->id, 'user_id' => $author->id]);

        $text = $this->export();

        $this->assertSame(1.0, $this->value($text, 'goat_users'));
        $this->assertSame(1.0, $this->value($text, 'goat_posts'));
        $this->assertSame(3.0, $this->value($text, 'goat_comments'));
        $this->assertSame(3.0, $this->value($text, 'goat_comments_created{window="1h"}'));
    }

    public function test_failed_jobs_and_tokens_are_counted(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'a', 'connection' => 'redis', 'queue' => 'scoring', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        DB::table('failed_jobs')->insert(['uuid' => 'b', 'connection' => 'redis', 'queue' => 'scoring', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDay()]);
        $user = User::factory()->create();
        RefreshToken::create(['user_id' => $user->id, 'token' => hash('sha256', 'live'), 'expires_at' => now()->addDay()]);
        RefreshToken::create(['user_id' => $user->id, 'token' => hash('sha256', 'dead'), 'expires_at' => now()->addDay(), 'revoked_at' => now()]);

        $text = $this->export();

        $this->assertSame(2.0, $this->value($text, 'goat_failed_jobs'));
        $this->assertSame(1.0, $this->value($text, 'goat_failed_jobs_last_hour'));
        $this->assertSame(1.0, $this->value($text, 'goat_refresh_tokens_active'));
        $this->assertSame(1.0, $this->value($text, 'goat_refresh_tokens_revoked_last_hour'));
    }

    public function test_a_broken_collector_is_reported_and_does_not_stop_the_export(): void
    {
        DB::statement('DROP TABLE failed_jobs');

        $text = $this->export();

        $this->assertSame(0.0, $this->value($text, 'goat_metrics_collector_success{collector="failed_jobs"}'));
        $this->assertSame(1.0, $this->value($text, 'goat_metrics_collector_success{collector="content"}'));
    }

    public function test_the_file_is_replaced_atomically_and_world_readable(): void
    {
        $this->export();
        $first = filemtime($this->path);
        $this->export();

        $this->assertSame('0644', substr(sprintf('%o', fileperms($this->path)), -4));
        $this->assertSame([], glob(dirname($this->path).'/*.tmp'));
        $this->assertGreaterThanOrEqual($first, filemtime($this->path));
    }

    public function test_label_values_are_escaped(): void
    {
        config(['monitoring.queues' => ['we"ird']]);

        $this->assertStringContainsString('goat_queue_size{queue="we\"ird"}', $this->export());
    }

    public function test_the_heartbeat_is_zero_until_a_worker_ran_it(): void
    {
        $this->assertSame(0.0, $this->value($this->export(), 'goat_queue_heartbeat_timestamp_seconds'));
    }

    public function test_the_heartbeat_shows_when_the_worker_last_ran_it(): void
    {
        (new QueueHeartbeat)->handle();

        $this->assertEqualsWithDelta(time(), $this->value($this->export(), 'goat_queue_heartbeat_timestamp_seconds'), 5);
    }

    public function test_product_metrics_describe_activity_activation_and_retention(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $author = User::factory()->create(['created_at' => '2026-01-01']);
        $post = Post::factory()->create(['user_id' => $author->id, 'created_at' => '2026-10-01']);
        $act = function (int $userId, string $at) use ($post) {
            DB::table('comments')->insert(['post_id' => $post->id, 'user_id' => $userId, 'content' => 'x', 'created_at' => $at, 'updated_at' => $at]);
        };
        $mk = fn (string $n, string $created) => DB::table('users')->insertGetId(['first_name' => $n, 'last_name' => 'T', 'username' => $n, 'email' => "{$n}@t.test", 'password' => 'x', 'created_at' => $created, 'updated_at' => $created]);

        // signed up 3 days ago, acted 2 hours after signing up: activated, not back on day 1
        $a = $mk('a', '2026-10-07 12:00:00');
        $act($a, '2026-10-07 14:00:00');
        // signed up 4 days ago, never did anything
        $mk('b', '2026-10-06 12:00:00');
        // signed up 20 days ago: acted on day 1 and on day 7, and again today
        $c = $mk('c', '2026-09-20 12:00:00');
        $act($c, '2026-09-21 15:00:00');
        $act($c, '2026-09-27 15:00:00');
        $act($c, '2026-10-10 09:00:00');
        // signed up 12 days ago, acted on day 3 only: back within the week, not on day 1 or 7
        $d = $mk('d', '2026-09-28 12:00:00');
        $act($d, '2026-10-01 12:30:00');

        $text = $this->export();

        // activity today: only c; this week: a (3d ago) and c (d acted 9 days ago); this month: a, c, d and the author (asked on Oct 1)
        $this->assertSame(1.0, $this->value($text, 'goat_active_users{window="1d"}'));
        $this->assertSame(2.0, $this->value($text, 'goat_active_users{window="7d"}'), 'a and c, d acted 9 days ago');
        $this->assertSame(4.0, $this->value($text, 'goat_active_users{window="30d"}'));

        // activation cohort = signed up 1-8 days ago = a, b, d(12d: no) -> a and b; a acted within a day
        $this->assertSame(0.5, $this->value($text, 'goat_activation_ratio'));
        $this->assertSame(2.0, $this->value($text, 'goat_activation_cohort_size'));

        // day 1: cohort signed up 2-30 days ago = a, b, c, d; only c acted on day 1
        $this->assertSame(0.25, $this->value($text, 'goat_retention_ratio{window="d1"}'));
        $this->assertSame(4.0, $this->value($text, 'goat_retention_cohort_size{window="d1"}'));
        // day 7: cohort 8-30 days ago = c, d; c acted on day 7
        $this->assertSame(0.5, $this->value($text, 'goat_retention_ratio{window="d7"}'));
        // came back within the first week: c and d of c, d
        $this->assertSame(1.0, $this->value($text, 'goat_retention_ratio{window="within7d"}'));

        // funnel over the last 30 days: a, b, c, d signed up (the author is older); 3 of them commented
        $this->assertSame(4.0, $this->value($text, 'goat_funnel_users{stage="signed_up"}'));
        $this->assertSame(3.0, $this->value($text, 'goat_funnel_users{stage="commented"}'));
        $this->assertSame(0.0, $this->value($text, 'goat_funnel_users{stage="posted"}'));
    }

    public function test_ratios_are_left_out_instead_of_shown_as_zero_when_nobody_is_in_the_cohort(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');

        $text = $this->export();

        $this->assertStringNotContainsString('goat_activation_ratio', $text);
        $this->assertStringNotContainsString('goat_retention_ratio', $text);
        $this->assertSame(0.0, $this->value($text, 'goat_active_users{window="30d"}'));
        $this->assertSame(1.0, $this->value($text, 'goat_metrics_collector_success{collector="product"}'));
    }

    public function test_question_traction_counts_posts_that_got_five_votes_on_their_first_day(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $author = User::factory()->create(['created_at' => '2026-01-01']);
        $hot = Post::factory()->create(['user_id' => $author->id, 'created_at' => '2026-10-05 10:00:00']);
        Post::factory()->create(['user_id' => $author->id, 'created_at' => '2026-10-06 10:00:00']); // no votes
        Post::factory()->create(['user_id' => $author->id, 'created_at' => '2026-10-09 18:00:00']); // too young for the cohort
        foreach (range(1, 5) as $i) {
            $voter = User::factory()->create(['created_at' => '2026-02-01']);
            DB::table('votes')->insert(['user_id' => $voter->id, 'post_id' => $hot->id, 'vote_option' => 'option_one', 'created_at' => '2026-10-05 11:00:00', 'updated_at' => '2026-10-05 11:00:00']);
        }

        $text = $this->export();

        $this->assertSame(0.5, $this->value($text, 'goat_question_traction_ratio'));
        $this->assertSame(2.0, $this->value($text, 'goat_question_traction_cohort_size'));
    }
}
