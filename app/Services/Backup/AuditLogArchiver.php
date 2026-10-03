<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use ZipArchive;

// Sends each audit line exactly once: every run takes what was appended since the last successful send,
// so the 23:55 run plus the 00:10 run cover the whole day without gaps or repeats.
class AuditLogArchiver
{
    private const SLICE_BYTES = 64 * 1024 * 1024;

    private const KEEP_DAYS = 14;

    // zip files of the complete lines written since the last send; each carries a commit() to call after its upload
    public function build(string $date, string $dir): array
    {
        $log = storage_path("logs/audit_trail-{$date}.log");
        if (!is_file($log)) {
            return [];
        }

        clearstatcache(true, $log);
        $size = filesize($log);
        $state = Cache::get($this->key($date), ['offset' => 0, 'parts' => 0]);
        $offset = (int) $state['offset'];
        $part = (int) $state['parts'];
        // the file shrank (rotated or cleared): start over rather than skip lines
        if ($size < $offset) {
            $offset = 0;
        }
        $closed = $date < now()->format('Y-m-d');

        $in = fopen($log, 'rb');
        $items = [];
        try {
            while ($offset < $size) {
                fseek($in, $offset);
                $chunk = (string) fread($in, min(self::SLICE_BYTES, $size - $offset));

                // only whole lines go out; a line still being written waits for the next run.
                // A day that has ended has no next line to wait for: everything goes.
                $atEnd = $offset + strlen($chunk) >= $size;
                $cut = $closed && $atEnd ? strlen($chunk) - 1 : strrpos($chunk, "\n");
                if ($cut === false) {
                    if (strlen($chunk) < self::SLICE_BYTES) {
                        break;
                    }
                    $cut = strlen($chunk) - 1; // one line longer than a slice
                }
                $chunk = substr($chunk, 0, $cut + 1);
                $end = $offset + strlen($chunk);
                $part++;

                $items[] = $this->zip($date, $dir, $part, $chunk, $end);
                $offset = $end;
            }
        } finally {
            fclose($in);
        }

        return $items;
    }

    private function zip(string $date, string $dir, int $part, string $chunk, int $end): array
    {
        $name = "audit_trail-{$date}_part{$part}.zip";
        $entry = "audit_trail-{$date}.part{$part}.log";

        $zip = new ZipArchive;
        if ($zip->open("{$dir}/{$name}", ZipArchive::CREATE) !== true) {
            throw new RuntimeException('cannot write the audit log zip');
        }
        $zip->addFromString($entry, $chunk);
        $zip->setCompressionName($entry, ZipArchive::CM_DEFLATE);
        if ($zip->close() !== true) {
            throw new RuntimeException('cannot finish the audit log zip (disk full?)');
        }

        return [
            'path' => "{$dir}/{$name}",
            'name' => $name,
            'date' => $date,
            'part' => $part,
            'lines' => substr_count($chunk, "\n"),
            'commit' => function () use ($date, $part, $end) {
                Cache::forever($this->key($date), ['offset' => $end, 'parts' => $part]);
                Cache::forget($this->key(now()->subDays(self::KEEP_DAYS)->format('Y-m-d')));
            },
        ];
    }

    private function key(string $date): string
    {
        return "backup:telegram:audit:{$date}";
    }
}
