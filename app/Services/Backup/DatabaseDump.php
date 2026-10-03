<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

// MySQL-native dump in PHP: one consistent snapshot, generated columns left to the server, streamed into gzip.
// (MariaDB's client dumps generated columns, which MySQL 8 refuses to import.)
class DatabaseDump
{
    private const ROWS_PER_INSERT = 500;

    private const BYTES_PER_INSERT = 1_000_000;

    private const NUMERIC = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real', 'year', 'bit'];

    private const BINARY = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];

    // PDO turns doubles into PHP floats and loses digits; the server's own text is exact
    private const EXACT_TEXT = ['decimal', 'numeric', 'float', 'double', 'real'];

    public function dumpTo(string $gzPath): int
    {
        $config = config('database.connections.'.config('database.default'));
        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('database backups need the mysql connection');
        }

        // own connection: the shared one must stay buffered and outside our snapshot
        $pdo = DB::connectUsing('backup-dump', $config, true)->getPdo();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

        $gz = gzopen($gzPath, 'wb9');
        try {
            $this->dump($pdo, $config['database'], $gz);
        } finally {
            gzclose($gz);
            DB::purge('backup-dump');
        }

        return $this->verify($gzPath);
    }

    // reads the whole file back: valid gzip, real dump, finished dump
    public function verify(string $gzPath): int
    {
        $gz = gzopen($gzPath, 'rb');
        $bytes = 0;
        $head = '';
        $tail = '';
        while (!gzeof($gz)) {
            $chunk = gzread($gz, 65536);
            if ($chunk === false) {
                throw new RuntimeException('the dump is not valid gzip');
            }
            $bytes += strlen($chunk);
            if (strlen($head) < 65536) {
                $head .= $chunk;
            }
            $tail = substr($tail.$chunk, -2048);
        }
        gzclose($gz);

        if (!str_contains($head, 'CREATE TABLE')) {
            throw new RuntimeException('the dump contains no tables');
        }
        if (!str_contains($tail, '-- Dump completed')) {
            throw new RuntimeException('the dump is truncated (no completion line)');
        }

        return $bytes;
    }

    private function dump(PDO $pdo, string $database, $gz): void
    {
        $this->guardUnsupported($pdo, $database);

        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        $tables = $this->names($pdo, 'BASE TABLE');
        $views = $this->names($pdo, 'VIEW');

        // everything the snapshot needs is read before the first (unbuffered) data query
        $plan = [];
        foreach ($tables as $table) {
            $plan[$table] = [
                'create' => $this->definition($pdo, 'SHOW CREATE TABLE '.$this->id($table)),
                'columns' => $this->columns($pdo, $database, $table),
            ];
        }

        $this->put($gz, "-- goat database dump\n-- host: ".gethostname()."\n-- database: {$database}\n\n"
            ."/*!40101 SET NAMES utf8mb4 */;\nSET time_zone = '+00:00';\nSET foreign_key_checks = 0;\nSET unique_checks = 0;\nSET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        foreach ($plan as $table => $info) {
            $this->put($gz, "DROP TABLE IF EXISTS {$this->id($table)};\n{$info['create']};\n\n");
            $this->rows($pdo, $gz, $table, $info['columns']);
        }

        foreach ($views as $view) {
            $create = $this->definition($pdo, 'SHOW CREATE VIEW '.$this->id($view));
            $this->put($gz, "DROP VIEW IF EXISTS {$this->id($view)};\n{$create};\n\n");
        }

        $pdo->exec('ROLLBACK');
        $this->put($gz, "SET foreign_key_checks = 1;\nSET unique_checks = 1;\n\n-- Dump completed on ".gmdate('Y-m-d H:i:s')." UTC\n");
    }

    // a backup that quietly skips part of the database is worse than none
    private function guardUnsupported(PDO $pdo, string $database): void
    {
        $queries = [
            'triggers' => 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?',
            'stored routines' => 'SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?',
            'events' => 'SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ?',
        ];
        foreach ($queries as $what => $sql) {
            $statement = $pdo->prepare($sql);
            $statement->execute([$database]);
            $count = (int) $statement->fetchColumn();
            $statement->closeCursor();
            if ($count > 0) {
                throw new RuntimeException("the database has {$what}, which this dumper does not export");
            }
        }
    }

    // an unbuffered connection allows one open result: read the row, then close it
    private function definition(PDO $pdo, string $sql): string
    {
        $statement = $pdo->query($sql);
        $row = $statement->fetch(PDO::FETCH_NUM);
        $statement->closeCursor();

        return $row[1];
    }

    private function names(PDO $pdo, string $type): array
    {
        return array_column($pdo->query("SHOW FULL TABLES WHERE Table_type = '{$type}'")->fetchAll(PDO::FETCH_NUM), 0);
    }

    // columns the server computes itself are not data (DEFAULT_GENERATED is an ordinary default and stays)
    private function columns(PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
        $statement->execute([$database, $table]);

        return array_values(array_filter(
            $statement->fetchAll(PDO::FETCH_ASSOC),
            fn ($column) => !preg_match('/(VIRTUAL|STORED) GENERATED/', $column['EXTRA'])
        ));
    }

    private function rows(PDO $pdo, $gz, string $table, array $columns): void
    {
        if ($columns === []) {
            return;
        }

        $names = implode(',', array_map(fn ($c) => $this->id($c['COLUMN_NAME']), $columns));
        $select = implode(',', array_map(fn ($c) => $this->selectable($c), $columns));
        $head = "INSERT INTO {$this->id($table)} ({$names}) VALUES\n";
        $result = $pdo->query("SELECT {$select} FROM {$this->id($table)}");

        $batch = [];
        $size = 0;
        while (($row = $result->fetch(PDO::FETCH_NUM)) !== false) {
            $values = [];
            foreach ($row as $i => $value) {
                $values[] = $this->literal($pdo, $value, $columns[$i]['DATA_TYPE']);
            }
            $tuple = '('.implode(',', $values).')';
            $batch[] = $tuple;
            $size += strlen($tuple);

            if (count($batch) >= self::ROWS_PER_INSERT || $size >= self::BYTES_PER_INSERT) {
                $this->put($gz, $head.implode(",\n", $batch).";\n");
                $batch = [];
                $size = 0;
            }
        }
        $result->closeCursor();

        if ($batch !== []) {
            $this->put($gz, $head.implode(",\n", $batch).";\n");
        }
        $this->put($gz, "\n");
    }

    private function selectable(array $column): string
    {
        $id = $this->id($column['COLUMN_NAME']);

        return match (true) {
            in_array($column['DATA_TYPE'], self::EXACT_TEXT, true) => "CAST({$id} AS CHAR)",
            $column['DATA_TYPE'] === 'bit' => "CAST({$id} AS UNSIGNED)",
            default => $id,
        };
    }

    private function literal(PDO $pdo, ?string $value, string $type): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (in_array($type, self::BINARY, true)) {
            return $value === '' ? "''" : '0x'.bin2hex($value);
        }
        if (in_array($type, self::NUMERIC, true) && is_numeric($value)) {
            return $value;
        }

        return $pdo->quote($value);
    }

    private function id(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function put($gz, string $data): void
    {
        if (gzwrite($gz, $data) === 0 && $data !== '') {
            throw new RuntimeException('write failed (disk full?)');
        }
    }
}
