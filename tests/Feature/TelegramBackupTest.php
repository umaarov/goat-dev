<?php

namespace Tests\Feature;

use App\Support\BackupCrypt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;
use ZipArchive;

class TelegramBackupTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '4f1c0b7d9a3e5c2b8d6f0a1e3c5b7d9f2a4c6e8b0d1f3a5c7e9b1d3f5a7c9e0b'; // gitleaks:allow

    private const TOKEN = '123456:SECRET-backup-token';

    private const DAY = '2001-01-01';

    // a day that has not ended yet, so the last half-written line must wait
    private const OPEN_DAY = '2999-01-01';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/tgbackup-'.uniqid();
        mkdir("{$this->tmp}/public", 0777, true);
        Sleep::fake();
        $this->telegram(Http::response(['ok' => true, 'result' => ['message_id' => 1]]));

        config([
            'backup.telegram.enabled' => true,
            'backup.telegram.token' => self::TOKEN,
            'backup.telegram.chat_id' => '-100999',
            'backup.telegram.api_url' => 'https://telegram.test',
            'backup.encrypt' => true,
            'backup.key' => self::KEY,
            'backup.tmp_dir' => "{$this->tmp}/work",
            'backup.part_bytes' => 45 * 1024 * 1024,
            'filesystems.disks.public.root' => "{$this->tmp}/public",
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        @unlink(storage_path('logs/audit_trail-'.self::DAY.'.log'));
        @unlink(storage_path('logs/audit_trail-'.self::OPEN_DAY.'.log'));
        Carbon::setTestNow();
        parent::tearDown();
    }

    // the first matching fake wins, so a test that wants another answer starts from a fresh factory
    private function telegram($response): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => $response]);
    }

    private function auditLog(string $content = "[2001-01-01 10:00:00 +00:00] local.INFO: [AUTH] [LOGIN] ok {}\n", string $day = self::DAY, bool $append = false): void
    {
        file_put_contents(storage_path("logs/audit_trail-{$day}.log"), $content, $append ? FILE_APPEND : 0);
    }

    // what each audit upload contained, in order
    private function auditParts(): array
    {
        return array_map(fn ($upload) => array_values($this->zipEntries($this->open($upload['bytes'])))[0], array_filter($this->uploads(), fn ($u) => str_starts_with($u['name'], 'audit_trail')));
    }

    // [filename, bytes, caption] of every sendDocument call
    private function uploads(): array
    {
        $out = [];
        foreach (Http::recorded() as [$request]) {
            if (!str_ends_with($request->url(), '/sendDocument')) {
                continue;
            }
            $parts = collect($request->data())->keyBy('name');
            $file = $parts['document'];
            $contents = $file['contents'];
            if (is_resource($contents)) {
                rewind($contents);
                $contents = stream_get_contents($contents);
            }
            $out[] = ['name' => $file['filename'], 'bytes' => $contents, 'caption' => $parts['caption']['contents'], 'chat' => $parts['chat_id']['contents']];
        }

        return $out;
    }

    private function open(string $encrypted): string
    {
        $in = "{$this->tmp}/in.enc";
        $out = "{$this->tmp}/out.bin";
        file_put_contents($in, $encrypted);
        BackupCrypt::decryptFile($in, $out, self::KEY);

        return $out;
    }

    private function zipEntries(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'not a valid zip');
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        ksort($entries);

        return $entries;
    }

    public function test_it_refuses_to_reach_the_real_telegram_from_a_non_production_machine(): void
    {
        config(['backup.telegram.api_url' => 'https://api.telegram.org']);
        $this->auditLog();

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])
            ->expectsOutputToContain('not production')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_it_never_sends_unencrypted_data_without_a_valid_key(): void
    {
        config(['backup.key' => null]);
        $this->auditLog();

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])
            ->expectsOutputToContain('nothing is sent unencrypted')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_ping_sends_a_message_to_the_configured_chat(): void
    {
        $this->artisan('backup:telegram', ['type' => 'ping'])->assertExitCode(0);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/bot'.self::TOKEN.'/sendMessage') && $r['chat_id'] === '-100999' && str_contains($r['text'], 'Backup channel check'));
    }

    public function test_the_audit_log_goes_out_as_an_encrypted_zip(): void
    {
        $this->auditLog("line one\nline two\n");

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $uploads = $this->uploads();
        $this->assertCount(1, $uploads);
        $this->assertSame('audit_trail-'.self::DAY.'_part1.zip.enc', $uploads[0]['name']);
        $this->assertSame('-100999', $uploads[0]['chat']);
        $this->assertStringStartsWith("GOATBK1\n", $uploads[0]['bytes']);
        $this->assertStringNotContainsString('line one', $uploads[0]['bytes']);
        $this->assertStringContainsString(substr(hash('sha256', $uploads[0]['bytes']), 0, 16), $uploads[0]['caption']);
        $this->assertStringContainsString('2 new lines', $uploads[0]['caption']);

        $entries = $this->zipEntries($this->open($uploads[0]['bytes']));
        $this->assertSame(['audit_trail-'.self::DAY.'.part1.log' => "line one\nline two\n"], $entries);
    }

    public function test_a_quiet_day_sends_nothing(): void
    {
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $this->assertSame([], $this->uploads());
        $this->assertTrue(Cache::get('backup:telegram:logs')['ok'], 'a quiet day is a healthy run');
    }

    public function test_every_audit_line_is_sent_exactly_once_across_runs(): void
    {
        $this->auditLog("a\nb\n");
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        // the 23:55 run is done; more events arrive before midnight
        $this->auditLog("c\nd\n", self::DAY, true);
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        // nothing new: nothing is sent again
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $names = array_column($this->uploads(), 'name');
        $this->assertSame(['audit_trail-'.self::DAY.'_part1.zip.enc', 'audit_trail-'.self::DAY.'_part2.zip.enc'], $names);
        $this->assertSame(["a\nb\n", "c\nd\n"], $this->auditParts());
        $this->assertSame(file_get_contents(storage_path('logs/audit_trail-'.self::DAY.'.log')), implode('', $this->auditParts()), 'the parts rebuild the log exactly');
    }

    public function test_a_line_that_is_still_being_written_waits_for_the_next_run(): void
    {
        $this->auditLog("a\nhalf of a li", self::OPEN_DAY);
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::OPEN_DAY])->assertExitCode(0);

        $this->auditLog("ne\nz\n", self::OPEN_DAY, true);
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::OPEN_DAY])->assertExitCode(0);

        $this->assertSame(["a\n", "half of a line\nz\n"], $this->auditParts());
    }

    public function test_a_closed_day_sends_its_unterminated_last_line_too(): void
    {
        $this->auditLog("a\nlast line without newline");

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $this->assertSame(["a\nlast line without newline"], $this->auditParts());
    }

    public function test_a_failed_upload_does_not_lose_progress_and_the_next_run_resends_it(): void
    {
        $this->auditLog("a\nb\n");
        $this->telegram(Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request'], 400));
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(1);

        $this->telegram(Http::response(['ok' => true, 'result' => ['message_id' => 1]]));
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $this->assertSame(["a\nb\n"], $this->auditParts());
        $this->assertSame(['audit_trail-'.self::DAY.'_part1.zip.enc'], array_column($this->uploads(), 'name'));
    }

    public function test_a_log_that_shrinks_is_sent_again_from_the_start_instead_of_skipping_lines(): void
    {
        $this->auditLog("one\ntwo\nthree\n");
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $this->auditLog("fresh\n");
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $this->assertSame(["one\ntwo\nthree\n", "fresh\n"], $this->auditParts());
    }

    public function test_yesterday_is_the_catch_up_for_the_lines_after_the_evening_run(): void
    {
        Carbon::setTestNow('2001-01-01 23:55:00');
        $this->auditLog("before\n");
        $this->artisan('backup:telegram', ['type' => 'logs'])->assertExitCode(0);

        $this->auditLog("23:58 event\n", self::DAY, true);
        Carbon::setTestNow('2001-01-02 00:10:00');
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => 'yesterday'])->assertExitCode(0);

        $this->assertSame(["before\n", "23:58 event\n"], $this->auditParts());
    }

    public function test_photos_are_split_into_independent_parts_and_skip_what_is_not_data(): void
    {
        $public = "{$this->tmp}/public";
        foreach (['avatars', 'post_images', 'temp'] as $d) {
            mkdir("{$public}/{$d}");
        }
        $wanted = [];
        foreach (['avatars/a.webp', 'avatars/b.webp', 'post_images/c.jpg', 'post_images/d.png', 'post_images/e.webp'] as $f) {
            file_put_contents("{$public}/{$f}", str_repeat(chr(65 + strlen($f) % 20), 600));
            $wanted[$f] = file_get_contents("{$public}/{$f}");
        }
        file_put_contents("{$public}/temp/x.jpg", 'temp');
        file_put_contents("{$public}/avatars.zip", 'a zip of everything');
        file_put_contents("{$public}/.gitignore", '*');
        symlink("{$public}/avatars/a.webp", "{$public}/link.webp");
        config(['backup.part_bytes' => 1500]);

        $this->artisan('backup:telegram', ['type' => 'photos'])->assertExitCode(0);

        $uploads = $this->uploads();
        $this->assertCount(3, $uploads);
        $this->assertMatchesRegularExpression('/^goat_photos_[\d_-]+_part1of3\.zip\.enc$/', $uploads[0]['name']);
        $this->assertStringContainsString('part3of3', $uploads[2]['name']);

        $restored = [];
        foreach ($uploads as $upload) {
            $restored += $this->zipEntries($this->open($upload['bytes']));
        }
        ksort($wanted);
        ksort($restored);
        $this->assertSame($wanted, $restored);
    }

    public function test_the_database_dump_is_complete_encrypted_and_restorable(): void
    {
        $this->artisan('backup:telegram', ['type' => 'db'])->assertExitCode(0);

        $uploads = $this->uploads();
        $this->assertCount(1, $uploads);
        $this->assertMatchesRegularExpression('/^goat_db_[\d_-]+\.sql\.gz\.enc$/', $uploads[0]['name']);

        $sql = gzdecode(file_get_contents($this->open($uploads[0]['bytes'])));
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('CREATE TABLE `posts`', $sql);
        $this->assertStringContainsString('-- Dump completed', $sql);
    }

    public function test_a_dump_that_cannot_be_verified_is_never_sent(): void
    {
        $this->mock(\App\Services\Backup\DatabaseDump::class, function ($mock) {
            $mock->shouldReceive('dumpTo')->once()->andThrow(new \RuntimeException('the dump is truncated (no completion line)'));
        });

        $this->artisan('backup:telegram', ['type' => 'db'])->expectsOutputToContain('truncated')->assertExitCode(1);

        $this->assertSame([], $this->uploads());
    }

    public function test_a_telegram_error_is_reported_without_leaking_the_token_and_marks_the_run_failed(): void
    {
        $this->telegram(Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400));
        $this->auditLog();

        $exit = Artisan::call('backup:telegram', ['type' => 'logs', '--date' => self::DAY]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('chat not found', $output);
        $this->assertStringNotContainsString(self::TOKEN, $output);
        $this->assertSame(['ok' => false], array_intersect_key(Cache::get('backup:telegram:logs'), ['ok' => 1]));

        // the failure notice goes to the same chat, and 4xx answers are not retried
        $sent = collect(Http::recorded())->map(fn ($pair) => basename($pair[0]->url()))->all();
        $this->assertSame(['sendDocument', 'sendMessage'], $sent);
        $this->assertSame([], glob("{$this->tmp}/work/run-*"), 'temp files must not survive a failure');
    }

    public function test_flood_control_is_waited_out_and_retried(): void
    {
        $this->telegram(Http::sequence()
            ->push(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 7]], 429)
            ->push(['ok' => true, 'result' => ['message_id' => 1]]));
        $this->auditLog();

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 8, 1);
        $this->assertCount(2, Http::recorded());
    }

    public function test_a_success_is_recorded_for_the_metrics_and_cleans_up(): void
    {
        $this->auditLog();
        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $state = Cache::get('backup:telegram:logs');
        $this->assertTrue($state['ok']);
        $this->assertEqualsWithDelta(time(), $state['at'], 5);
        $this->assertSame([], glob("{$this->tmp}/work/run-*"));

        $path = "{$this->tmp}/m.prom";
        $this->artisan('app:export-metrics', ['--path' => $path])->assertExitCode(0);
        $metrics = file_get_contents($path);
        $this->assertStringContainsString('goat_backup_last_success_timestamp_seconds{type="logs"}', $metrics);
        $this->assertStringContainsString('goat_backup_last_run_ok{type="logs"} 1', $metrics);
        $this->assertStringContainsString('goat_backup_enabled 1', $metrics);
    }

    public function test_a_dry_run_builds_everything_but_sends_nothing_even_where_sending_is_refused(): void
    {
        config(['backup.telegram.api_url' => 'https://api.telegram.org']);
        $this->auditLog();

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY, '--dry-run' => true, '--keep' => "{$this->tmp}/kept"])->assertExitCode(0);

        Http::assertNothingSent();
        $kept = glob("{$this->tmp}/kept/*.enc");
        $this->assertCount(1, $kept);
        $this->assertNull(Cache::get('backup:telegram:logs'), 'a dry run is not a backup');
    }

    public function test_plaintext_has_to_be_switched_on_explicitly(): void
    {
        config(['backup.encrypt' => false, 'backup.key' => null]);
        $this->auditLog();

        $this->artisan('backup:telegram', ['type' => 'logs', '--date' => self::DAY])->assertExitCode(0);

        $upload = $this->uploads()[0];
        $this->assertSame('audit_trail-'.self::DAY.'_part1.zip', $upload['name']);
        $this->assertStringContainsString('PLAIN', $upload['caption']);
    }

    public function test_the_schedule_has_the_three_backups_at_the_agreed_times(): void
    {
        Artisan::call('schedule:list');
        $list = Artisan::output();

        $this->assertMatchesRegularExpression('/55\s+23\s+\*\s+\*\s+\*\s+.*backup:telegram logs/', $list);
        $this->assertMatchesRegularExpression('/10\s+0\s+\*\s+\*\s+\*\s+.*backup:telegram logs --date=yesterday/', $list);
        $this->assertMatchesRegularExpression('/0\s+4\s+\*\s+\*\s+\*\s+.*backup:telegram db/', $list);
        $this->assertMatchesRegularExpression('/15\s+4\s+\*\s+\*\s+\*\s+.*backup:telegram photos/', $list);
    }
}
