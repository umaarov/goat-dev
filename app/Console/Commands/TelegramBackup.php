<?php

namespace App\Console\Commands;

use App\Services\Backup\AuditLogArchiver;
use App\Services\Backup\DatabaseDump;
use App\Services\Backup\PhotoArchiver;
use App\Services\Backup\TelegramBackupSender;
use App\Support\BackupCrypt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

class TelegramBackup extends Command
{
    public const TYPES = ['db', 'photos', 'logs'];

    protected $signature = 'backup:telegram
        {type : db, photos, logs, all or ping}
        {--dry-run : build, encrypt and verify, send nothing}
        {--keep= : with --dry-run, copy the finished files into this directory}
        {--date= : logs only, the day to send (Y-m-d or yesterday), default today}';

    protected $description = 'Encrypt a backup and send it to the backup Telegram channel';

    public function handle(TelegramBackupSender $telegram): int
    {
        $type = (string) $this->argument('type');
        $dry = (bool) $this->option('dry-run');

        if (!in_array($type, [...self::TYPES, 'all', 'ping'], true)) {
            $this->error('type must be db, photos, logs, all or ping');

            return self::INVALID;
        }

        if (!$dry && ($reason = $telegram->refusal())) {
            $this->error("refusing to send: {$reason}");

            return self::FAILURE;
        }

        if ($type === 'ping') {
            return $this->ping($telegram);
        }

        if ($this->encrypting() && !BackupCrypt::validKey(config('backup.key'))) {
            $this->error('BACKUP_ENCRYPTION_KEY must be 64 hex characters (openssl rand -hex 32); nothing is sent unencrypted');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($type === 'all' ? ['logs', 'db', 'photos'] : [$type] as $one) {
            try {
                $this->runOne($one, $telegram, $dry);
            } catch (Throwable $e) {
                $failed = true;
                $this->failOne($one, $e, $telegram, $dry);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function ping(TelegramBackupSender $telegram): int
    {
        if ($this->option('dry-run')) {
            $this->info('ping: nothing to build');

            return self::SUCCESS;
        }
        $telegram->message('✅ Backup channel check from '.gethostname().' ('.app()->environment().'). Backups arrive here encrypted.', true);
        $this->info('ping sent');

        return self::SUCCESS;
    }

    private function runOne(string $type, TelegramBackupSender $telegram, bool $dry): void
    {
        $dir = $this->workDir($type);
        $started = microtime(true);

        try {
            $stamp = now()->format('Y-m-d_H-i-s');
            $items = match ($type) {
                'db' => $this->database($dir, $stamp),
                'photos' => $this->photos($dir, $stamp),
                'logs' => $this->logs($dir),
            };

            if ($items === []) {
                $this->warn("{$type}: nothing to back up");
                Log::channel('audit_trail')->info("[BACKUP] [{$type}] Nothing to back up.");
                // a quiet audit log is a healthy run, not a stale backup
                if ($type === 'logs' && !$dry) {
                    $this->remember($type, []);
                }

                return;
            }

            $files = array_map(fn ($item) => $this->seal($item), $items);

            if ($dry) {
                $this->keep($files);
            } else {
                foreach ($files as $file) {
                    $telegram->document($file['path'], $file['name'], $file['caption']);
                    if ($file['commit']) {
                        ($file['commit'])(); // progress is saved per upload, a later failure never resends or skips
                    }
                    $this->line("sent {$file['name']} (".$this->size($file['bytes']).')');
                    Sleep::for(1200)->milliseconds(); // one message per second per chat
                }
                $this->remember($type, $files);
            }

            $seconds = round(microtime(true) - $started, 1);
            Log::channel('audit_trail')->info("[BACKUP] [{$type}] ".($dry ? 'Built (dry run).' : 'Sent to Telegram.'), [
                'files' => count($files), 'bytes' => array_sum(array_column($files, 'bytes')), 'seconds' => $seconds,
            ]);
            $this->info("{$type}: ".count($files).' file(s), '.$this->size(array_sum(array_column($files, 'bytes')))." in {$seconds}s".($dry ? ' (dry run)' : ''));
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function database(string $dir, string $stamp): array
    {
        $plain = "{$dir}/goat_db_{$stamp}.sql.gz";
        $bytes = app(DatabaseDump::class)->dumpTo($plain);

        return [[
            'path' => $plain, 'name' => basename($plain),
            'note' => "🗄 Database\n".$this->size($bytes).' of SQL',
        ]];
    }

    private function photos(string $dir, string $stamp): array
    {
        $parts = app(PhotoArchiver::class)->build(
            config('filesystems.disks.public.root'),
            $dir,
            $stamp,
            (int) config('backup.part_bytes')
        );

        return array_map(fn ($part) => [
            'path' => $part['path'], 'name' => $part['name'],
            'note' => "🖼 Photos, part {$part['part']} of {$part['parts']}\n{$part['files']} files",
        ], $parts);
    }

    // the audit lines written since the last send (see AuditLogArchiver)
    private function logs(string $dir): array
    {
        $date = match ($this->option('date')) {
            null, '' => now()->format('Y-m-d'),
            'yesterday' => now()->subDay()->format('Y-m-d'),
            default => $this->option('date'),
        };
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('--date must look like 2026-10-03 or yesterday');
        }

        return array_map(fn ($item) => [
            'path' => $item['path'], 'name' => $item['name'], 'commit' => $item['commit'],
            'note' => "📜 Audit log {$item['date']}, part {$item['part']}\n{$item['lines']} new lines",
        ], app(AuditLogArchiver::class)->build($date, $dir));
    }

    // encrypt (unless switched off), prove the result decrypts to the original, describe it
    private function seal(array $item): array
    {
        $plain = $item['path'];
        $path = $plain;

        if ($this->encrypting()) {
            $path = $plain.'.enc';
            BackupCrypt::encryptFile($plain, $path, (string) config('backup.key'));

            $check = $plain.'.check';
            BackupCrypt::decryptFile($path, $check, (string) config('backup.key'));
            if (hash_file('sha256', $check) !== hash_file('sha256', $plain)) {
                throw new RuntimeException("{$item['name']} does not decrypt to the original");
            }
            unlink($check);
            unlink($plain);
        }

        $bytes = filesize($path);
        if ($bytes > 49 * 1024 * 1024) {
            throw new RuntimeException(basename($path).' is over the 50 MB upload limit');
        }
        $sha = hash_file('sha256', $path);

        return [
            'path' => $path,
            'name' => basename($path),
            'bytes' => $bytes,
            'sha256' => $sha,
            'commit' => $item['commit'] ?? null,
            'caption' => $item['note'].' · '.$this->size($bytes).($this->encrypting() ? ' · encrypted' : ' · PLAIN')."\nsha256 ".substr($sha, 0, 16).'…'."\n".gethostname().' · '.now()->format('Y-m-d H:i T'),
        ];
    }

    private function keep(array $files): void
    {
        if (!($target = $this->option('keep'))) {
            return;
        }
        File::ensureDirectoryExists($target);
        foreach ($files as $file) {
            File::copy($file['path'], rtrim($target, '/').'/'.$file['name']);
        }
    }

    // read by app:export-metrics
    private function remember(string $type, array $files): void
    {
        Cache::forever("backup:telegram:{$type}", [
            'ok' => true,
            'at' => time(),
            'bytes' => array_sum(array_column($files, 'bytes')),
            'files' => count($files),
        ]);
    }

    private function failOne(string $type, Throwable $e, TelegramBackupSender $telegram, bool $dry): void
    {
        $reason = $telegram->scrub($e->getMessage());
        $this->error("{$type} failed: {$reason}");
        Log::channel('audit_trail')->error("[BACKUP] [{$type}] Failed.", ['error' => $reason]);

        if ($dry) {
            return;
        }

        $previous = Cache::get("backup:telegram:{$type}", []);
        Cache::forever("backup:telegram:{$type}", array_merge($previous, ['ok' => false, 'failed_at' => time()]));

        try {
            $telegram->message("❌ Backup {$type} FAILED on ".gethostname().":\n".mb_substr($reason, 0, 600));
        } catch (Throwable) {
            // Telegram itself is the problem: the log and the metric still say it
        }
    }

    private function workDir(string $type): string
    {
        $base = (string) config('backup.tmp_dir');
        File::ensureDirectoryExists($base, 0700);

        // leftovers of a killed run
        foreach (glob("{$base}/run-*", GLOB_ONLYDIR) ?: [] as $old) {
            if (filemtime($old) < time() - 6 * 3600) {
                File::deleteDirectory($old);
            }
        }

        $dir = "{$base}/run-{$type}-".bin2hex(random_bytes(4));
        mkdir($dir, 0700);

        return $dir;
    }

    private function encrypting(): bool
    {
        return (bool) config('backup.encrypt');
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
