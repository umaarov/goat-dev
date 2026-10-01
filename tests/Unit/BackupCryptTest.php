<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class BackupCryptTest extends TestCase
{
    private const KEY = '4f1c0b7d9a3e5c2b8d6f0a1e3c5b7d9f2a4c6e8b0d1f3a5c7e9b1d3f5a7c9e0b';

    private const OTHER_KEY = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    private function crypt(string $mode, string $input, string $key = self::KEY): array
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/docker/backup-crypt.php', $mode], null, ['BACKUP_KEY' => $key], $input);
        $process->run();

        return [$process->getExitCode(), $process->getOutput(), trim($process->getErrorOutput())];
    }

    #[DataProvider('sizes')]
    public function test_round_trip_across_block_boundaries(int $size): void
    {
        $plain = $size === 0 ? '' : random_bytes($size);

        [$code, $cipher] = $this->crypt('encrypt', $plain);
        $this->assertSame(0, $code);
        $this->assertStringStartsWith("GOATBK1\n", $cipher);

        [$code, $decrypted] = $this->crypt('decrypt', $cipher);
        $this->assertSame(0, $code);
        $this->assertSame($plain, $decrypted);
    }

    public static function sizes(): array
    {
        return [[0], [1], [65535], [65536], [65537], [131072], [250000]];
    }

    public function test_plaintext_is_not_visible_and_ciphertext_is_randomised(): void
    {
        $plain = str_repeat('secret-user@example.com ', 5000);

        [, $a] = $this->crypt('encrypt', $plain);
        [, $b] = $this->crypt('encrypt', $plain);

        $this->assertStringNotContainsString('secret-user', $a);
        $this->assertNotSame($a, $b);
    }

    public function test_wrong_key_flipped_byte_truncation_and_junk_are_all_rejected(): void
    {
        [, $cipher] = $this->crypt('encrypt', random_bytes(131072));

        $flipped = $cipher;
        $flipped[intdiv(strlen($flipped), 2)] = chr(ord($flipped[intdiv(strlen($flipped), 2)]) ^ 1);

        $cases = [
            'wrong key' => [$cipher, self::OTHER_KEY, 'wrong key or backup was modified'],
            'flipped byte' => [$flipped, self::KEY, 'wrong key or backup was modified'],
            'last block removed' => [substr($cipher, 0, -(65536 + 17 + 4)), self::KEY, 'truncated backup'],
            'cut inside block' => [substr($cipher, 0, -5), self::KEY, 'truncated backup'],
            'junk appended' => [$cipher.'junk', self::KEY, 'backup-crypt'],
            'not a backup' => ['plain text file', self::KEY, 'not a GOATBK1 backup'],
        ];

        foreach ($cases as $name => [$data, $key, $message]) {
            [$code, , $error] = $this->crypt('decrypt', $data, $key);

            $this->assertNotSame(0, $code, "{$name} was accepted");
            $this->assertStringContainsString($message, $error, $name);
        }
    }

    public function test_a_malformed_key_is_refused(): void
    {
        [$code, , $error] = $this->crypt('encrypt', 'data', 'short');

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('BACKUP_KEY must be 64 hex', $error);
    }
}
