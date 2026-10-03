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
            'backups' => fn () => $this->backups(),
            'product' => fn () => $this->product(),
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

    // who uses the product, not just how much exists; heavier queries, so cached for five minutes
    private function product(): void
    {
        $p = Cache::remember('metrics:product', 300, fn () => $this->productNumbers());

        foreach ($p['active'] as $window => $count) {
            $this->gauge('goat_active_users', 'Distinct users who voted, commented or asked a question in the window', $count, ['window' => $window]);
        }
        foreach ($p['ratios'] as $name => [$ratio, $cohort]) {
            $this->gauge("goat_{$name}_ratio", "Share of the cohort ({$name})", $ratio, []);
            $this->gauge("goat_{$name}_cohort_size", "Size of the cohort behind goat_{$name}_ratio", $cohort, []);
        }
        foreach ($p['retention'] as $window => [$ratio, $cohort]) {
            $this->gauge('goat_retention_ratio', 'Share of a signup cohort that came back', $ratio, ['window' => $window]);
            $this->gauge('goat_retention_cohort_size', 'Size of the cohort behind goat_retention_ratio', $cohort, ['window' => $window]);
        }
        foreach ($p['funnel'] as $stage => $count) {
            $this->gauge('goat_funnel_users', 'Users who signed up in the last 30 days and reached the stage', $count, ['stage' => $stage]);
        }
    }

    private function productNumbers(): array
    {
        $activity = 'SELECT user_id, created_at FROM votes UNION ALL SELECT user_id, created_at FROM comments UNION ALL SELECT user_id, created_at FROM posts';
        $now = now();

        $active = [];
        foreach (['1d' => 1, '7d' => 7, '30d' => 30] as $window => $days) {
            $active[$window] = (int) DB::scalar("SELECT COUNT(DISTINCT user_id) FROM ({$activity}) a WHERE created_at >= ?", [$now->copy()->subDays($days)]);
        }

        // acted within a day of signing up: cohort = signed up 1 to 8 days ago, so everyone had a full day
        $acted = fn (\Closure $within) => "(EXISTS (SELECT 1 FROM votes x WHERE x.user_id = u.id AND {$within('x')}) OR EXISTS (SELECT 1 FROM comments x WHERE x.user_id = u.id AND {$within('x')}) OR EXISTS (SELECT 1 FROM posts x WHERE x.user_id = u.id AND {$within('x')}))";
        $ratios = [];

        $activation = DB::selectOne('SELECT COUNT(*) AS cohort, COALESCE(SUM'.$acted(fn ($t) => "{$t}.created_at < DATE_ADD(u.created_at, INTERVAL 1 DAY)").', 0) AS hit FROM users u WHERE u.deleted_at IS NULL AND u.created_at >= ? AND u.created_at < ?', [$now->copy()->subDays(8), $now->copy()->subDay()]);
        if ($activation->cohort > 0) {
            $ratios['activation'] = [round($activation->hit / $activation->cohort, 4), (int) $activation->cohort];
        }

        // a question has traction when it gets five votes in its first day: cohort = asked 1 to 8 days ago
        $traction = DB::selectOne('SELECT COUNT(*) AS cohort, COALESCE(SUM((SELECT COUNT(*) FROM votes v WHERE v.post_id = p.id AND v.created_at < DATE_ADD(p.created_at, INTERVAL 1 DAY)) >= 5), 0) AS hit FROM posts p WHERE p.deleted_at IS NULL AND p.created_at >= ? AND p.created_at < ?', [$now->copy()->subDays(8), $now->copy()->subDay()]);
        if ($traction->cohort > 0) {
            $ratios['question_traction'] = [round($traction->hit / $traction->cohort, 4), (int) $traction->cohort];
        }

        // classic day-N retention (active on day N after signup) and "came back at all in the first week"
        $retention = [];
        $windows = [
            'd1' => ['from' => 1, 'to' => 2, 'min_age' => 2],
            'd7' => ['from' => 7, 'to' => 8, 'min_age' => 8],
            'within7d' => ['from' => 1, 'to' => 8, 'min_age' => 8],
        ];
        foreach ($windows as $name => $w) {
            $row = DB::selectOne('SELECT COUNT(*) AS cohort, COALESCE(SUM'.$acted(fn ($t) => "{$t}.created_at >= DATE_ADD(u.created_at, INTERVAL {$w['from']} DAY) AND {$t}.created_at < DATE_ADD(u.created_at, INTERVAL {$w['to']} DAY)").', 0) AS hit FROM users u WHERE u.deleted_at IS NULL AND u.created_at >= ? AND u.created_at < ?', [$now->copy()->subDays(30), $now->copy()->subDays($w['min_age'])]);
            if ($row->cohort > 0) {
                $retention[$name] = [round($row->hit / $row->cohort, 4), (int) $row->cohort];
            }
        }

        $row = DB::selectOne('SELECT COUNT(*) AS signed_up, COALESCE(SUM(EXISTS (SELECT 1 FROM votes x WHERE x.user_id = u.id)), 0) AS voted, COALESCE(SUM(EXISTS (SELECT 1 FROM comments x WHERE x.user_id = u.id)), 0) AS commented, COALESCE(SUM(EXISTS (SELECT 1 FROM posts x WHERE x.user_id = u.id)), 0) AS posted FROM users u WHERE u.deleted_at IS NULL AND u.created_at >= ?', [$now->copy()->subDays(30)]);
        $funnel = ['signed_up' => (int) $row->signed_up, 'voted' => (int) $row->voted, 'commented' => (int) $row->commented, 'posted' => (int) $row->posted];

        return ['active' => $active, 'ratios' => $ratios, 'retention' => $retention, 'funnel' => $funnel];
    }

    private function backups(): void
    {
        $enabled = config('backup.telegram.enabled') && config('backup.telegram.token') && config('backup.telegram.chat_id');
        $this->gauge('goat_backup_enabled', 'Telegram backups are configured to run', $enabled ? 1 : 0);

        foreach (TelegramBackup::TYPES as $type) {
            $state = Cache::get("backup:telegram:{$type}");
            if (!is_array($state)) {
                continue;
            }
            $labels = ['type' => $type];
            if (isset($state['at'])) {
                $this->gauge('goat_backup_last_success_timestamp_seconds', 'Unix time of the last backup that reached Telegram', $state['at'], $labels);
                $this->gauge('goat_backup_last_size_bytes', 'Size of the last backup that reached Telegram', $state['bytes'] ?? 0, $labels);
            }
            if (isset($state['failed_at'])) {
                $this->gauge('goat_backup_last_failure_timestamp_seconds', 'Unix time of the last failed backup', $state['failed_at'], $labels);
            }
            $this->gauge('goat_backup_last_run_ok', '1 when the latest backup run succeeded, 0 when it failed', ($state['ok'] ?? false) ? 1 : 0, $labels);
        }
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
