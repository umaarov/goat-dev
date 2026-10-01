#!/usr/bin/env bash
# backup/ -> local docker stack, anonymized. Wipes local db + photos first.
# login: admin@goat.local (user 1) or userN@goat.local, password: password
set -euo pipefail
cd "$(dirname "$0")/.."

dc() { docker compose -f docker-compose.full-local.yml "$@"; }
sql() { dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --default-character-set=utf8mb4 "$@"' sh "$@"; }

DUMP=$(ls -t backup/goat_db_*.sql.gz | head -1)
PHOTOS=$(ls -t backup/goat_photos_*.tar.gz | head -1)
AUDIT=$(ls -t backup/audit_trail-*.log.gz 2>/dev/null | head -1 || true)

dc stop app worker scheduler >/dev/null 2>&1 || true
dc up -d db redis mailpit
until [ "$(docker inspect -f '{{.State.Health.Status}}' goat-local-db-1)" = healthy ]; do sleep 2; done

DB=$(dc exec -T db printenv MYSQL_DATABASE | tr -d '\r')
HASH=$(docker run --rm --entrypoint php goat-app:local -r 'echo password_hash("password", PASSWORD_BCRYPT);')

echo "db: $DUMP"
sql -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`; CREATE DATABASE IF NOT EXISTS goat_test;"
gunzip -c "$DUMP" | sql "$DB"

echo "anonymize"
sql "$DB" <<SQL
SET FOREIGN_KEY_CHECKS=0;
UPDATE users SET
  email = IF(id = 1, 'admin@goat.local', CONCAT('user', id, '@goat.local')),
  original_email = NULL,
  password = '$HASH',
  remember_token = NULL,
  email_verification_token = NULL,
  google_id = NULL, x_id = NULL, x_username = NULL,
  telegram_id = NULL, telegram_username = NULL, github_id = NULL;
TRUNCATE personal_access_tokens;
TRUNCATE refresh_tokens;
TRUNCATE device_tokens;
TRUNCATE sessions;
TRUNCATE unsubscribe_tokens;
TRUNCATE notification_schedules;
TRUNCATE referral_clicks;
TRUNCATE failed_jobs;
TRUNCATE jobs;
TRUNCATE job_batches;
TRUNCATE cache;
TRUNCATE cache_locks;
TRUNCATE pulse_entries;
TRUNCATE pulse_aggregates;
TRUNCATE pulse_values;
SET FOREIGN_KEY_CHECKS=1;
SQL

echo "photos: $PHOTOS"
docker volume inspect goat-local_goat-storage >/dev/null 2>&1 || docker volume create \
  --label com.docker.compose.project=goat-local --label com.docker.compose.volume=goat-storage goat-local_goat-storage >/dev/null
docker run --rm -v goat-local_goat-storage:/data -v "$PWD/backup":/backup:ro alpine sh -c \
  "find /data -mindepth 1 -delete && tar -xzf /backup/$(basename "$PHOTOS") -C /data && chown -R 10001:10001 /data"

if [ -n "$AUDIT" ]; then
  gunzip -c "$AUDIT" | sed -E 's/"ip_address":"[^"]*"/"ip_address":"0.0.0.0"/g' > "storage/logs/$(basename "${AUDIT%.gz}")"
fi

dc exec -T redis sh -c 'redis-cli -a "$REDIS_PASSWORD" --no-auth-warning flushall' >/dev/null
dc up -d
until [ "$(docker inspect -f '{{.State.Health.Status}}' goat-local-app-1)" = healthy ]; do sleep 2; done
dc exec -T app php artisan migrate --force
echo "done: http://127.0.0.1:8000"
