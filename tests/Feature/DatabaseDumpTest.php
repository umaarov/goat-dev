<?php

namespace Tests\Feature;

use App\Services\Backup\DatabaseDump;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;

// no RefreshDatabase: the dump runs on its own connection and only sees committed rows
class DatabaseDumpTest extends TestCase
{
    use DatabaseTruncation;

    private const RESTORE_DB = 'zz_backup_restore';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/dump-'.uniqid();
        mkdir($this->tmp);

        DB::statement('DROP TABLE IF EXISTS zz_backup_edge');
        DB::statement('CREATE TABLE zz_backup_edge (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            bin BINARY(4) NULL, vbin VARBINARY(32) NULL, blobby BLOB NULL,
            amount DECIMAL(12,4) NULL, ratio DOUBLE NULL, txt TEXT NULL,
            flags BIT(8) NULL, js JSON NULL, kind ENUM(\'a\',\'b\') NULL,
            at TIMESTAMP NULL, amount_x2 DECIMAL(14,4) GENERATED ALWAYS AS (amount * 2) VIRTUAL,
            stamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TABLE IF EXISTS zz_backup_edge');
        DB::statement('DROP DATABASE IF EXISTS '.self::RESTORE_DB);
        array_map('unlink', glob($this->tmp.'/*') ?: []);
        rmdir($this->tmp);
        parent::tearDown();
    }

    private function pdo(?string $database = null): PDO
    {
        $c = config('database.connections.'.config('database.default'));

        return new PDO("mysql:host={$c['host']};port={$c['port']};".($database ? "dbname={$database};" : '').'charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function dump(): string
    {
        $path = "{$this->tmp}/dump.sql.gz";
        app(DatabaseDump::class)->dumpTo($path);

        return gzdecode(file_get_contents($path));
    }

    // PDO only reports a later statement's error when asked for the next result set
    private function restore(string $sql): PDO
    {
        $this->pdo()->exec('DROP DATABASE IF EXISTS '.self::RESTORE_DB.'; CREATE DATABASE '.self::RESTORE_DB.' CHARACTER SET utf8mb4');
        $pdo = $this->pdo(self::RESTORE_DB);
        $statement = $pdo->query($sql);
        do {
            // consume every statement's result
        } while ($statement->nextRowset());

        return $pdo;
    }

    private function checksum(string $database, string $table): string
    {
        return (string) $this->pdo()->query("CHECKSUM TABLE `{$database}`.`{$table}`")->fetch(PDO::FETCH_NUM)[1];
    }

    public function test_the_dump_restores_into_an_identical_database_including_nasty_values(): void
    {
        $nasty = "O'Reilly \"quoted\" back\\slash; -- not a comment\nnew line\ttab 😀 ünï \0 nul";
        DB::table('zz_backup_edge')->insert([
            ['bin' => "\x00\x01\xff\x10", 'vbin' => '', 'blobby' => random_bytes(300), 'amount' => '-12345678.1234', 'ratio' => '3.141592653589793', 'txt' => $nasty, 'flags' => "\xAA", 'js' => '{"a":[1,2,"x\"y"],"é":null}', 'kind' => 'b', 'at' => '2026-10-03 04:00:00'],
            ['bin' => null, 'vbin' => null, 'blobby' => null, 'amount' => null, 'ratio' => null, 'txt' => '', 'flags' => null, 'js' => null, 'kind' => null, 'at' => null],
        ]);
        DB::table('users')->insert(['first_name' => 'Zed', 'last_name' => "D'Arc", 'username' => 'zed', 'email' => 'zed@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pulse_entries')->insert(['timestamp' => 1790000000, 'type' => 'slow', 'key' => 'GET /x', 'value' => 7]);

        $sql = $this->dump();

        // generated columns are the server's business: neither dumped nor imported
        $this->assertStringNotContainsString('`key_hash`) VALUES', $sql);
        $this->assertStringNotContainsString('`amount_x2`) VALUES', $sql);
        $this->assertStringContainsString('-- Dump completed', $sql);

        $this->restore($sql);

        $database = config('database.connections.'.config('database.default').'.database');
        foreach (['zz_backup_edge', 'users', 'pulse_entries', 'posts', 'migrations'] as $table) {
            $this->assertSame($this->checksum($database, $table), $this->checksum(self::RESTORE_DB, $table), "{$table} differs after the restore");
        }
        $this->assertSame(2, (int) $this->pdo(self::RESTORE_DB)->query('SELECT COUNT(*) FROM zz_backup_edge')->fetchColumn());

        // exact, not merely equal after rounding
        $exact = 'SELECT CAST(ratio AS CHAR), CAST(flags AS UNSIGNED), HEX(bin), HEX(blobby), amount, txt, js FROM %s.zz_backup_edge ORDER BY id';
        $this->assertSame(
            $this->pdo()->query(sprintf($exact, $database))->fetchAll(PDO::FETCH_NUM),
            $this->pdo(self::RESTORE_DB)->query(sprintf($exact, self::RESTORE_DB))->fetchAll(PDO::FETCH_NUM)
        );
        $this->assertSame('3.141592653589793', $this->pdo(self::RESTORE_DB)->query('SELECT CAST(ratio AS CHAR) FROM zz_backup_edge WHERE ratio IS NOT NULL')->fetchColumn());
        $this->assertSame('170', (string) $this->pdo(self::RESTORE_DB)->query('SELECT CAST(flags AS UNSIGNED) FROM zz_backup_edge WHERE flags IS NOT NULL')->fetchColumn());
        $this->assertSame(
            md5('GET /x'),
            bin2hex($this->pdo(self::RESTORE_DB)->query('SELECT key_hash FROM pulse_entries LIMIT 1')->fetchColumn()),
            'the server recomputes generated columns'
        );
    }

    public function test_a_large_table_is_split_into_many_inserts_and_restores_completely(): void
    {
        $rows = [];
        for ($i = 0; $i < 1300; $i++) {
            $rows[] = ['txt' => "row {$i}", 'ratio' => $i / 7];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('zz_backup_edge')->insert($chunk);
        }

        $sql = $this->dump();
        $this->assertGreaterThanOrEqual(3, substr_count($sql, 'INSERT INTO `zz_backup_edge`'));

        $this->restore($sql);
        $this->assertSame(1300, (int) $this->pdo(self::RESTORE_DB)->query('SELECT COUNT(*) FROM zz_backup_edge')->fetchColumn());
    }

    public function test_a_database_with_triggers_is_refused_instead_of_being_backed_up_incompletely(): void
    {
        DB::unprepared('CREATE TRIGGER zz_backup_trigger BEFORE INSERT ON zz_backup_edge FOR EACH ROW SET NEW.txt = NEW.txt');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('triggers');
            app(DatabaseDump::class)->dumpTo("{$this->tmp}/never.sql.gz");
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS zz_backup_trigger');
        }
    }

    public function test_verification_rejects_truncated_empty_and_broken_files(): void
    {
        $dump = app(DatabaseDump::class);
        $cases = [
            'truncated' => [gzencode("CREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n"), 'truncated'],
            'no tables' => [gzencode("-- Dump completed on 2026-10-03\n"), 'no tables'],
            'plain text' => ['definitely not a dump', 'no tables'],
        ];

        foreach ($cases as $name => [$data, $message]) {
            file_put_contents("{$this->tmp}/bad.gz", $data);
            try {
                $dump->verify("{$this->tmp}/bad.gz");
                $this->fail("{$name} was accepted");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }

        file_put_contents("{$this->tmp}/ok.gz", gzencode("CREATE TABLE t (id int);\n-- Dump completed on 2026-10-03\n"));
        $this->assertGreaterThan(0, $dump->verify("{$this->tmp}/ok.gz"));
    }
}
