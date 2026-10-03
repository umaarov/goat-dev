<?php

namespace Tests\Feature;

use App\Jobs\QueueHeartbeat;
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
}
