#!/usr/bin/env bash
# encrypted backup: db + uploads -> backup/*.enc (XChaCha20-Poly1305, see docker/backup-crypt.php)
# key:   openssl rand -hex 32 > ~/.goat-backup.key   (keep it OFF the server that holds the backups)
# stack: COMPOSE_FILE (default docker-compose.yml), BACKUP_DIR (default backup), KEEP (default 14 per kind)
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE_FILE=${COMPOSE_FILE:-docker-compose.yml}
OUT=${BACKUP_DIR:-backup}
KEEP=${KEEP:-14}
KEY_FILE=${BACKUP_KEY_FILE:-$HOME/.goat-backup.key}

[ -r "$KEY_FILE" ] || { echo "missing key file $KEY_FILE (create: openssl rand -hex 32 > $KEY_FILE; chmod 600 $KEY_FILE)" >&2; exit 1; }
BACKUP_KEY=$(tr -d '[:space:]' < "$KEY_FILE")
export BACKUP_KEY

dc() { docker compose -f "$COMPOSE_FILE" "$@"; }
# plain `docker run` of the stack's own image: no compose build/run side effects on the data stream
APP_ID=$(dc ps -q app)
[ -n "$APP_ID" ] || { echo "the app container is not running" >&2; exit 1; }
IMAGE=$(docker inspect -f '{{.Config.Image}}' "$APP_ID")
crypt() { docker run --rm -i -e BACKUP_KEY --entrypoint php "$IMAGE" /app/docker/backup-crypt.php "$1"; }

umask 077
mkdir -p "$OUT"
ts=$(date +%F_%H-%M-%S)

# write to .partial, verify by decrypting, only then give it its real name
finish() { mv "$1.partial" "$1"; echo "ok  $1 ($(wc -c < "$1" | tr -d ' ') bytes)"; }
trap 'rm -f "$OUT"/*.partial' EXIT

db="$OUT/goat_db_$ts.sql.gz.enc"
dc exec -T db sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" exec mysqldump --single-transaction --quick --no-tablespaces -uroot \"\$MYSQL_DATABASE\"" \
  | gzip -9 | crypt encrypt > "$db.partial"
crypt decrypt < "$db.partial" | gunzip -t
finish "$db"

photos="$OUT/goat_photos_$ts.tar.gz.enc"
dc exec -T app tar -czf - -C /app/storage/app/public . | crypt encrypt > "$photos.partial"
crypt decrypt < "$photos.partial" | tar -tz > /dev/null
finish "$photos"

# retention: newest $KEEP of each kind (encrypted files only, old plain backups are left alone)
shopt -s nullglob
for kind in goat_db_ goat_photos_; do
  files=("$OUT/${kind}"*.enc)
  for ((i = 0; i < ${#files[@]} - KEEP; i++)); do
    rm -f -- "${files[i]}"
  done
done
