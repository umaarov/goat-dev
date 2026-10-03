<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ExportMetrics extends Command
{
    protected $signature = 'app:export-metrics {--path= : target .prom file}';

    protected $description = 'Write app and business metrics for the node-exporter textfile collector';

    private array $metrics = [];

    public function handle(): int
    {
        $path = $this->option('path') ?: config('monitoring.textfile_path');

        $collectors = [
            'queues' => fn () => $this->queues(),
            'failed_jobs' => fn () => $this->failedJobs(),
            'content' => fn () => $this->content(),
            'tokens' => fn () => $this->tokens(),
        ];

        foreach ($collectors as $name => $collect) {
            try {
                $collect();
                $ok = 1;
            } catch (\Throwable $e) {
                report($e);
                $ok = 0;
            }
            $this->gauge('goat_metrics_collector_success', 'Collector finished without error', $ok, ['collector' => $name]);
        }

        $this->gauge('goat_metrics_export_timestamp_seconds', 'Unix time of the last export', time());
        $this->gauge('goat_app_info', 'Static build info', 1, ['laravel' => app()->version(), 'php' => PHP_VERSION, 'env' => app()->environment()]);

        return $this->write($path) ? self::SUCCESS : self::FAILURE;
    }

    private function queues(): void
    {
        // 0 until a worker has run the heartbeat job at least once
        $this->gauge('goat_queue_heartbeat_timestamp_seconds', 'Unix time the worker last ran the heartbeat job', (int) Cache::get(QueueHeartbeat::KEY, 0));

        foreach ((array) config('monitoring.queues') as $queue) {
            $this->gauge('goat_queue_size', 'Jobs waiting, delayed or reserved', Queue::size($queue), ['queue' => $queue]);
        }
    }

    private function failedJobs(): void
    {
        $this->gauge('goat_failed_jobs', 'Rows in failed_jobs', DB::table('failed_jobs')->count());
        $this->gauge('goat_failed_jobs_last_hour', 'Jobs that failed in the last hour', DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count());
    }

    private function content(): void
    {
        foreach (['users', 'posts', 'comments', 'votes'] as $table) {
            // deactivated users and deleted posts are not live content
            $live = fn () => DB::table($table)->when(in_array($table, ['users', 'posts'], true), fn ($q) => $q->whereNull('deleted_at'));

            $this->gauge("goat_{$table}", "Live rows in {$table}", $live()->count());

            foreach (['1h' => now()->subHour(), '24h' => now()->subDay()] as $window => $since) {
                $this->gauge("goat_{$table}_created", "Rows in {$table} created in the window", $live()->where('created_at', '>=', $since)->count(), ['window' => $window]);
            }
        }
    }

    private function tokens(): void
    {
        $this->gauge('goat_refresh_tokens_active', 'Refresh tokens that can still be used', DB::table('refresh_tokens')->whereNull('revoked_at')->where('expires_at', '>', now())->count());
        $this->gauge('goat_refresh_tokens_revoked_last_hour', 'Refresh tokens revoked in the last hour', DB::table('refresh_tokens')->where('revoked_at', '>=', now()->subHour())->count());
    }

    private function gauge(string $name, string $help, int|float $value, array $labels = []): void
    {
        $pairs = [];
        foreach ($labels as $key => $label) {
            $pairs[] = $key.'="'.addcslashes((string) $label, "\\\"\n").'"';
        }

        $this->metrics[$name]['help'] = $help;
        $this->metrics[$name]['samples'][] = $name.($pairs ? '{'.implode(',', $pairs).'}' : '').' '.$value;
    }

    // a metric family must stay contiguous in the text format
    private function render(): string
    {
        $out = [];
        foreach ($this->metrics as $name => $metric) {
            $out[] = "# HELP {$name} {$metric['help']}";
            $out[] = "# TYPE {$name} gauge";
            array_push($out, ...$metric['samples']);
        }

        return implode("\n", $out)."\n";
    }

    // rename is atomic: the collector never reads a half-written file
    private function write(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error("cannot create {$dir}");

            return false;
        }

        $temp = $path.'.'.getmypid().'.tmp';
        if (file_put_contents($temp, $this->render()) === false || !chmod($temp, 0644) || !rename($temp, $path)) {
            @unlink($temp);
            $this->error("cannot write {$path}");

            return false;
        }

        return true;
    }
}
