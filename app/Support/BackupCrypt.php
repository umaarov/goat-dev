<?php

namespace App\Support;

use RuntimeException;

// authenticated streaming encryption (libsodium secretstream), same format as docker/backup-crypt.php
class BackupCrypt
{
    private const MAGIC = "GOATBK1\n";

    private const CHUNK = 65536;

    public static function validKey(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^[0-9a-f]{64}$/i', $hex) === 1;
    }

    public static function encryptFile(string $from, string $to, string $hexKey): void
    {
        self::guard($hexKey);
        $key = sodium_hex2bin($hexKey);
        $in = self::open($from, 'rb');
        $out = self::open($to, 'wb');

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            self::write($out, self::MAGIC.$header);

            $current = self::read($in, self::CHUNK);
            while (true) {
                $next = self::read($in, self::CHUNK);
                $final = $next === '';
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $current,
                    '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
                );
                self::write($out, pack('N', strlen($cipher)).$cipher);

                if ($final) {
                    break;
                }
                $current = $next;
            }
        } finally {
            sodium_memzero($key);
            fclose($in);
            fclose($out);
        }
    }

    public static function decryptFile(string $from, string $to, string $hexKey): void
    {
        self::guard($hexKey);
        $key = sodium_hex2bin($hexKey);
        $in = self::open($from, 'rb');
        $out = self::open($to, 'wb');

        try {
            if (self::read($in, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('not a GOATBK1 backup');
            }
            $header = self::read($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('truncated header');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $max = self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            while (true) {
                $lengthBytes = self::read($in, 4);
                if (strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('truncated backup (no final block)');
                }
                $length = unpack('N', $lengthBytes)[1];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > $max) {
                    throw new RuntimeException('corrupt backup (bad block length)');
                }
                $cipher = self::read($in, $length);
                if (strlen($cipher) !== $length) {
                    throw new RuntimeException('truncated backup (short block)');
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('wrong key or backup was modified');
                }
                [$plain, $tag] = $result;
                self::write($out, $plain);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if (self::read($in, 1) !== '') {
                        throw new RuntimeException('unexpected data after the final block');
                    }

                    return;
                }
            }
        } finally {
            sodium_memzero($key);
            fclose($in);
            fclose($out);
        }
    }

    private static function guard(string $hexKey): void
    {
        if (!self::validKey($hexKey)) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be 64 hex characters (openssl rand -hex 32)');
        }
    }

    private static function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("cannot open {$path}");
        }

        return $handle;
    }

    private static function read($handle, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && !feof($handle)) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                break;
            }
            $data .= $part;
        }

        return $data;
    }

    private static function write($handle, string $data): void
    {
        if ($data !== '' && fwrite($handle, $data) !== strlen($data)) {
            throw new RuntimeException('write failed (disk full?)');
        }
    }
}
