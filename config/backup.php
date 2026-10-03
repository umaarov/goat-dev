<?php

return [
    // the backup bot and channel, NOT the bot that posts questions (TELEGRAM_BOT_TOKEN)
    'telegram' => [
        'enabled' => (bool) env('BACKUP_TELEGRAM_ENABLED', env('APP_ENV') === 'production'),
        'token' => env('BACKUP_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('BACKUP_TELEGRAM_CHAT_ID'),
        'api_url' => rtrim((string) env('BACKUP_TELEGRAM_API_URL', 'https://api.telegram.org'), '/'),
    ],

    // GOATBK1 stream encryption, same format as docker/backup-crypt.php (openssl rand -hex 32)
    'encrypt' => (bool) env('BACKUP_ENCRYPT', true),
    'key' => env('BACKUP_ENCRYPTION_KEY'),

    'tmp_dir' => env('BACKUP_TMP_DIR', storage_path('backup-tmp')),

    // bots may upload 50 MB; stay below it
    'part_bytes' => (int) env('BACKUP_PART_BYTES', 45 * 1024 * 1024),
];
