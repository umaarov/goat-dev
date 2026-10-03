# Telegram backups

The scheduler (production only) sends three encrypted backups to the private backup channel:

| What | When | File |
|---|---|---|
| Audit log: the day so far | 23:55 | `audit_trail-YYYY-MM-DD_part1.zip.enc` |
| Audit log: the rest of that day | 00:10 | `audit_trail-YYYY-MM-DD_part2.zip.enc` (and further parts) |
| Database | 04:00 | `goat_db_<stamp>.sql.gz.enc` |
| Photos (uploads) | 04:15 | `goat_photos_<stamp>_part1ofN.zip.enc` (parts < 45 MB, each restores on its own) |

Times follow `APP_TIMEZONE`.

**Every audit line is sent exactly once.** Each run sends only what was appended since the last successful upload (progress is kept in the cache and saved per upload), so the 23:55 run and the 00:10 catch-up leave no gap and no repeat. A line that is still being written waits for the next run; a failed upload is retried from the same place; a log that shrinks is sent again from the start rather than skipped. To get a whole day back, join its parts in order: `unzip -p audit_trail-D_part1.zip; unzip -p audit_trail-D_part2.zip` (each backup run also logs its own `[BACKUP]` lines, which travel with the next run). Everything is encrypted (GOATBK1, libsodium) before it leaves the server; Telegram only stores ciphertext.

## Setup (production `.env`)

```
BACKUP_TELEGRAM_BOT_TOKEN=<@GoatBackupBot token>
BACKUP_TELEGRAM_CHAT_ID=-1003665577423
BACKUP_ENCRYPTION_KEY=<64 hex>            # same value as ~/.goat-backup.key on your machine
```
The bot must be an admin of the channel. Then `docker compose up -d` (scheduler picks the settings up) and check:

```
docker compose exec scheduler php artisan backup:telegram ping       # one message in the channel
docker compose exec scheduler php artisan backup:telegram logs       # sends today's audit log
docker compose exec scheduler php artisan backup:telegram all        # everything, now
```
Off in every other environment (`BACKUP_TELEGRAM_ENABLED` defaults to `APP_ENV=production`); a dev machine refuses to send even with the token set.

**The key is the backup.** Lose it and nothing can be opened. Keep a copy in a password manager, never only on the server.

## Restore

Download the files from the channel into `backup/`, then:

```
# decrypt any file (needs the key on stdin env, run from the repo)
BACKUP_KEY=$(cat ~/.goat-backup.key) php docker/backup-crypt.php decrypt < goat_db_<stamp>.sql.gz.enc > goat_db.sql.gz

# database (the dump drops and recreates every table; foreign keys are handled)
gunzip -c goat_db.sql.gz | docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"'

# photos: decrypt every part, unzip them all into the uploads volume
for p in goat_photos_<stamp>_part*.zip.enc; do BACKUP_KEY=$(cat ~/.goat-backup.key) php docker/backup-crypt.php decrypt < "$p" > "${p%.enc}"; done
for z in goat_photos_<stamp>_part*.zip; do unzip -o "$z" -d photos_restore; done
docker compose cp photos_restore/. app:/app/storage/app/public/
```
Audit log of a day: `for p in audit_trail-D_part*.zip.enc; do decrypt it, then unzip -p; done > audit_trail-D.log` (parts in order).
A wrong key or a damaged file is refused (authenticated encryption), never silently restored.

## What it checks by itself

- the dump is one consistent snapshot, reads generated columns correctly and ends with a completion line (a truncated dump is never sent);
- every file is decrypted again and compared with the original before upload;
- a failed run sends `❌ Backup <type> FAILED` to the same channel and logs `[BACKUP]` in the audit trail;
- Prometheus alerts `BackupStale` (nothing for 30 h), `BackupLastRunFailed`, `BackupNeverRan`.

Not backed up on purpose: `temp/` uploads and the stray `avatars.zip`.

## Test it locally

`make local-up && make monitoring-up && make local-backup-test` runs the real pipeline against a mock Telegram, decrypts with the script above, restores the database into a scratch database and compares every table and every photo, and checks that a late audit event arrives exactly once in the catch-up part.
