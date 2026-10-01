<?php

// authenticated streaming encryption for backups (libsodium secretstream, XChaCha20-Poly1305)
// BACKUP_KEY=<64 hex chars> php backup-crypt.php encrypt|decrypt < in > out
// tampered or truncated input fails with a non-zero exit code

const MAGIC = "GOATBK1\n";
const CHUNK = 65536;

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "backup-crypt: {$message}\n");
    exit($code);
}

function readExact($stream, int $length): string
{
    $data = '';
    while (strlen($data) < $length && !feof($stream)) {
        $part = fread($stream, $length - strlen($data));
        if ($part === false || $part === '') {
            break;
        }
        $data .= $part;
    }

    return $data;
}

$mode = $argv[1] ?? '';
$hex = (string) getenv('BACKUP_KEY');

if (!in_array($mode, ['encrypt', 'decrypt'], true)) {
    fail('usage: php backup-crypt.php encrypt|decrypt', 2);
}
if (!preg_match('/^[0-9a-f]{64}$/i', $hex)) {
    fail('BACKUP_KEY must be 64 hex characters (openssl rand -hex 32)', 2);
}

$key = sodium_hex2bin($hex);
$in = STDIN;
$out = STDOUT;

if ($mode === 'encrypt') {
    [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
    fwrite($out, MAGIC.$header);

    $current = readExact($in, CHUNK);
    while (true) {
        $next = readExact($in, CHUNK);
        $final = $next === '';
        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
            $state,
            $current,
            '',
            $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
        );
        fwrite($out, pack('N', strlen($cipher)).$cipher);

        if ($final) {
            break;
        }
        $current = $next;
    }
    exit(0);
}

if (readExact($in, strlen(MAGIC)) !== MAGIC) {
    fail('not a GOATBK1 backup (or wrong file)');
}

$header = readExact($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
    fail('truncated header');
}

$state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
$maxLength = CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

while (true) {
    $lengthBytes = readExact($in, 4);
    if (strlen($lengthBytes) !== 4) {
        fail('truncated backup (no final block)');
    }

    $length = unpack('N', $lengthBytes)[1];
    if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > $maxLength) {
        fail('corrupt backup (bad block length)');
    }

    $cipher = readExact($in, $length);
    if (strlen($cipher) !== $length) {
        fail('truncated backup (short block)');
    }

    $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
    if ($result === false) {
        fail('wrong key or backup was modified');
    }

    [$plain, $tag] = $result;
    fwrite($out, $plain);

    if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
        if (readExact($in, 1) !== '') {
            fail('unexpected data after the final block');
        }
        exit(0);
    }
}
