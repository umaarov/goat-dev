<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ShowAppLogs extends Command
{
    protected $signature = 'app:show-logs
                            {--lines=20 : Number of lines to show from the end of the log file.}
                            {--channel=audit_trail : The log channel to display (e.g., audit_trail, laravel, single).}
                            {--date= : Specific date for daily logs (YYYY-MM-DD). Defaults to today for daily channels.}
                            {--grep= : Filter lines containing this string (case-insensitive).}
                            {--tail : Continuously tail the log file (Ctrl+C to stop).}';

    protected $description = 'Display the last X lines from a specified application log channel, with filtering and tailing options.';

    public function handle(): int
    {
        $linesToShow = (int)$this->option('lines');
        $channelName = $this->option('channel');
        $grepPattern = $this->option('grep');
        $tailMode = $this->option('tail');
        $specificDate = $this->option('date');

        $logChannelConfig = config("logging.channels.{$channelName}");

        if (!$logChannelConfig) {
            $this->error("Log channel '{$channelName}' is not defined in config/logging.php.");
            return Command::FAILURE;
        }

        $logPath = $logChannelConfig['path'] ?? null;
        if (!$logPath) {
            $this->error("Path for log channel '{$channelName}' is not defined or channel is not file-based.");
            return Command::FAILURE;
        }

        if (($logChannelConfig['driver'] ?? 'single') === 'daily') {
            $dateSuffix = $specificDate ?: now()->format('Y-m-d');
            $baseName = pathinfo($logPath, PATHINFO_FILENAME);
            $extension = pathinfo($logPath, PATHINFO_EXTENSION);
            $directory = dirname($logPath);
            $logPath = $directory . '/' . $baseName . '-' . $dateSuffix . '.' . $extension;
        }

        if (!File::exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            if (($logChannelConfig['driver'] ?? 'single') === 'daily' && !$specificDate) {
                $this->info("Tip: No logs for today? Try specifying a date with --date=YYYY-MM-DD if logs exist for other days.");
            }
            return Command::FAILURE;
        }

        if ($tailMode) {
            return $this->tailLog($logPath, $grepPattern);
        } else {
            return $this->displayLog($logPath, $linesToShow, $grepPattern);
        }
    }

    protected function displayLog(string $logPath, int $linesToShow, ?string $grepPattern): int
    {
        try {
            $content = File::get($logPath);
            $logLines = collect(explode(PHP_EOL, $content))->filter(function ($line) {
                return !empty(trim($line));
            });

            if ($grepPattern) {
                $logLines = $logLines->filter(function ($line) use ($grepPattern) {
                    return Str::contains(strtolower($line), strtolower($grepPattern));
                });
            }

            $selectedLines = $logLines->slice(-$linesToShow)->values();

            if ($selectedLines->isEmpty()) {
                $this->info("No log entries found" . ($grepPattern ? " matching '{$grepPattern}'" : "") . " in {$logPath}.");
            } else {
                $this->info("Showing last " . $selectedLines->count() . " lines from: " . basename($logPath) . ($grepPattern ? " (filtered by '{$grepPattern}')" : ""));
                $this->output->newLine();
                $selectedLines->each(fn($line) => $this->line($line));
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Error reading log file {$logPath}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function tailLog(string $logPath, ?string $grepPattern): int
    {
        $this->info("Tailing log file: " . basename($logPath) . ($grepPattern ? " | grep -i '{$grepPattern}'" : ""));
        $this->info("Press Ctrl+C to exit.");
        $this->output->newLine();

        $handle = fopen($logPath, 'r');
        if (!$handle) {
            $this->error("Could not open log file: {$logPath}");
            return Command::FAILURE;
        }

        fseek($handle, 0, SEEK_END);
        $lastKnownSize = ftell($handle);

        try {
            while (true) {
                clearstatcache(true, $logPath);
                $currentSize = filesize($logPath);

                if ($currentSize < $lastKnownSize) {
                    $this->line("--- Log file may have been rotated or truncated. Re-opening: " . basename($logPath) . " ---");
                    fclose($handle);
                    $handle = fopen($logPath, 'r');
                    if (!$handle) {
                        $this->error("Could not re-open log file: {$logPath}");
                        return Command::FAILURE;
                    }
                } else {
                    fseek($handle, $lastKnownSize);
                }


                while (($line = fgets($handle)) !== false) {
                    $line = rtrim($line, "\r\n");
                    if (!empty($line)) {
                        if ($grepPattern) {
                            if (Str::contains(strtolower($line), strtolower($grepPattern))) {
                                $this->line($line);
                            }
                        } else {
                            $this->line($line);
                        }
                    }
                }
                $lastKnownSize = ftell($handle);
                usleep(250000);
            }
        } catch (Exception $e) {
            $this->error("Error tailing log file: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            if ($handle) {
                fclose($handle);
            }
        }
        // @codeCoverageIgnoreStart
        return Command::SUCCESS;
        // @codeCoverageIgnoreEnd
    }
}

//  php artisan app:show-logs (shows last 20 lines from audit_trail for today)
//  php artisan app:show-logs --lines=50
//  php artisan app:show-logs --channel=laravel (to view the default Laravel log for today)
//  php artisan app:show-logs --channel=single (for the single log driver)
//  php artisan app:show-logs --date=2025-05-23 (for a specific date, uses audit_trail by default)
//  php artisan app:show-logs --channel=audit_trail --date=2025-05-23
//  php artisan app:show-logs --grep="User ID: 123" (filter lines containing "User ID: 123")
//  php artisan app:show-logs --grep="authenticated"
//  php artisan app:show-logs --tail (continuously watch the audit_trail log for today)
//  php artisan app:show-logs --tail --grep="deleted"
