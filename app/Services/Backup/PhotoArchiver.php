<?php

namespace App\Services\Backup;

use RuntimeException;
use ZipArchive;

class PhotoArchiver
{
    // zip files are opened when the archive closes, so close it often (open-file limit)
    private const FILES_PER_BATCH = 100;

    private const ALREADY_COMPRESSED = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'mp4', 'zip'];

    // temp uploads and the stray avatars.zip (a zip of everything) are not data
    private const SKIP_TOP_LEVEL = ['temp', 'avatars.zip'];

    // independent zip parts below $partBytes of source data: each part restores on its own
    public function build(string $root, string $dir, string $stamp, int $partBytes): array
    {
        $files = $this->files($root);
        if ($files === []) {
            return [];
        }

        $this->guardDisk($dir, array_sum(array_column($files, 1)));

        $parts = [];
        $zip = null;
        $i = -1;

        $open = function (string $path) use (&$zip): void {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE) !== true) {
                throw new RuntimeException("cannot write {$path}");
            }
        };
        $close = function () use (&$zip): void {
            if ($zip && $zip->close() !== true) {
                throw new RuntimeException('cannot finish the zip (disk full?)');
            }
            $zip = null;
        };

        foreach ($files as [$path, $size, $relative]) {
            if ($size > $partBytes) {
                throw new RuntimeException("{$relative} is larger than one upload ({$size} bytes)");
            }

            if ($i < 0 || $parts[$i]['bytes'] + $size > $partBytes) {
                $close();
                $i++;
                $parts[$i] = ['path' => sprintf('%s/photos-%s-part%02d.zip', $dir, $stamp, $i + 1), 'bytes' => 0, 'files' => 0];
                $open($parts[$i]['path']);
            } elseif ($parts[$i]['files'] > 0 && $parts[$i]['files'] % self::FILES_PER_BATCH === 0) {
                $close();
                $open($parts[$i]['path']);
            }

            $zip->addFile($path, $relative);
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            $zip->setCompressionName($relative, in_array($extension, self::ALREADY_COMPRESSED, true) ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE);
            $parts[$i]['bytes'] += $size;
            $parts[$i]['files']++;
        }
        $close();

        return $this->name($parts, $dir, $stamp);
    }

    // [absolute path, size, path inside the zip], stable order, symlinks and unreadable files skipped
    private function files(string $root): array
    {
        if (!is_dir($root)) {
            throw new RuntimeException("photo directory {$root} does not exist");
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink() || !$file->isReadable()) {
                continue;
            }
            $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');
            if (in_array(explode('/', $relative)[0], self::SKIP_TOP_LEVEL, true) || str_starts_with(basename($relative), '.')) {
                continue;
            }
            $found[] = [$file->getPathname(), $file->getSize(), $relative];
        }
        usort($found, fn ($a, $b) => strcmp($a[2], $b[2]));

        return $found;
    }

    private function guardDisk(string $dir, int $sourceBytes): void
    {
        $free = @disk_free_space($dir);
        if ($free !== false && $free < $sourceBytes * 1.1 + 50 * 1024 * 1024) {
            throw new RuntimeException('not enough free disk space for the photo archive');
        }
    }

    // photos-<stamp>-partNN.zip -> goat_photos_<stamp>_part1of3.zip
    private function name(array $parts, string $dir, string $stamp): array
    {
        $total = count($parts);
        $named = [];
        foreach ($parts as $i => $part) {
            $final = sprintf('goat_photos_%s_part%dof%d.zip', $stamp, $i + 1, $total);
            rename($part['path'], "{$dir}/{$final}");
            $named[] = ['path' => "{$dir}/{$final}", 'name' => $final, 'files' => $part['files'], 'part' => $i + 1, 'parts' => $total];
        }

        return $named;
    }
}
