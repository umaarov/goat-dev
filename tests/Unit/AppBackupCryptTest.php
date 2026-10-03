<?php

namespace Tests\Unit;

use App\Support\BackupCrypt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class AppBackupCryptTest extends TestCase
{
    private const KEY = '4f1c0b7d9a3e5c2b8d6f0a1e3c5b7d9f2a4c6e8b0d1f3a5c7e9b1d3f5a7c9e0b'; // gitleaks:allow

    private const OTHER = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff'; // gitleaks:allow

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/crypt-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    private function put(string $name, string $data): string
    {
        file_put_contents("{$this->dir}/{$name}", $data);

        return "{$this->dir}/{$name}";
    }

    public static function sizes(): array
    {
        return [[0], [1], [65535], [65536], [65537], [200000]];
    }

    #[DataProvider('sizes')]
    public function test_round_trip(int $size): void
    {
        $plain = $this->put('plain', ($size > 0 ? random_bytes($size) : ''));

        BackupCrypt::encryptFile($plain, "{$this->dir}/enc", self::KEY);
        BackupCrypt::decryptFile("{$this->dir}/enc", "{$this->dir}/out", self::KEY);

        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', "{$this->dir}/out"));
        $this->assertStringStartsWith("GOATBK1\n", file_get_contents("{$this->dir}/enc"));
    }

    #[DataProvider('sizes')]
    public function test_the_existing_script_decrypts_what_the_app_encrypts_and_the_reverse(int $size): void
    {
        $script = dirname(__DIR__, 2).'/docker/backup-crypt.php';
        $plain = $this->put('plain', ($size > 0 ? random_bytes($size) : ''));

        BackupCrypt::encryptFile($plain, "{$this->dir}/enc", self::KEY);
        $viaScript = new Process([PHP_BINARY, $script, 'decrypt'], null, ['BACKUP_KEY' => self::KEY], file_get_contents("{$this->dir}/enc"));
        $viaScript->run();
        $this->assertSame(0, $viaScript->getExitCode(), $viaScript->getErrorOutput());
        $this->assertSame(file_get_contents($plain), $viaScript->getOutput());

        $fromScript = new Process([PHP_BINARY, $script, 'encrypt'], null, ['BACKUP_KEY' => self::KEY], file_get_contents($plain));
        $fromScript->run();
        $this->put('enc2', $fromScript->getOutput());
        BackupCrypt::decryptFile("{$this->dir}/enc2", "{$this->dir}/out2", self::KEY);
        $this->assertSame(file_get_contents($plain), file_get_contents("{$this->dir}/out2"));
    }

    public function test_tampering_truncation_and_wrong_keys_are_refused(): void
    {
        $plain = $this->put('plain', random_bytes(150000));
        BackupCrypt::encryptFile($plain, "{$this->dir}/enc", self::KEY);
        $cipher = file_get_contents("{$this->dir}/enc");

        $cases = [
            'wrong key' => [$cipher, self::OTHER, 'wrong key or backup was modified'],
            'flipped byte' => [substr_replace($cipher, chr(ord($cipher[100]) ^ 1), 100, 1), self::KEY, 'wrong key or backup was modified'],
            'cut inside a block' => [substr($cipher, 0, -5), self::KEY, 'truncated backup'],
            'last block removed' => [substr($cipher, 0, -(65536 + 17 + 4)), self::KEY, 'truncated backup'],
            'junk appended' => [$cipher.'junk', self::KEY, 'unexpected data'],
            'not a backup' => ['plain text', self::KEY, 'not a GOATBK1 backup'],
        ];

        foreach ($cases as $name => [$data, $key, $message]) {
            $this->put('bad', $data);
            try {
                BackupCrypt::decryptFile("{$this->dir}/bad", "{$this->dir}/never", $key);
                $this->fail("{$name} was accepted");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
    }

    public function test_bad_keys_are_rejected_up_front(): void
    {
        $this->expectException(RuntimeException::class);
        BackupCrypt::encryptFile($this->put('p', 'x'), "{$this->dir}/e", 'short');
    }
}
