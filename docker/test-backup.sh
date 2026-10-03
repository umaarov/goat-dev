#!/usr/bin/env bash
# local end-to-end check of the Telegram backups: real db + photos + audit log -> mock Telegram -> decrypt with the
# existing script -> restore into a scratch database -> compare. needs: make local-up && make monitoring-up
set -euo pipefail
cd "$(dirname "$0")/.."

MOCK=http://127.0.0.1:8081
KEY=$(openssl rand -hex 32)
MARK="e2e-late-$(openssl rand -hex 4)"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"; dc exec -T db sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" mysql -uroot -e \"DROP DATABASE IF EXISTS backup_verify\"" >/dev/null 2>&1 || true' EXIT

dc() { docker compose -f docker-compose.full-local.yml "$@"; }
sql() { dc exec -T db sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" mysql -uroot -N \"\$@\"" sh "$@"; }
step() { printf '\n== %s\n' "$*"; }
fail() { echo "FAIL: $*" >&2; exit 1; }
decrypt() { docker run --rm -i -e BACKUP_KEY="$KEY" --entrypoint php goat-app:local /app/docker/backup-crypt.php decrypt; }

curl -fsS -o /dev/null "$MOCK/" || fail "mock Telegram is not running (make monitoring-up)"
curl -fsS -o /dev/null "$MOCK/reset"

step "reset today's audit-log progress, so this run starts at the first line"
dc exec -T scheduler php artisan tinker --execute="Cache::forget('backup:telegram:audit:'.now()->format('Y-m-d'));"

step "run all three backups in the scheduler (photos in ~5 MB parts to exercise the split)"
dc exec -T \
  -e BACKUP_TELEGRAM_ENABLED=true -e BACKUP_TELEGRAM_BOT_TOKEN=0:local-mock -e BACKUP_TELEGRAM_CHAT_ID=1 \
  -e BACKUP_TELEGRAM_API_URL=http://mock-telegram:8081 -e BACKUP_ENCRYPTION_KEY="$KEY" -e BACKUP_PART_BYTES=5000000 \
  scheduler php artisan backup:telegram all

step "an event arrives after the evening run: the catch-up run must send exactly the new lines"
dc exec -T scheduler sh -c "echo \"[\$(date '+%F %T %z' | sed -E 's/(..)\$/:\\1/')] local.INFO: [BACKUP] $MARK after the evening run {}\" >> /app/storage/logs/audit_trail-\$(date +%F).log"
dc exec -T \
  -e BACKUP_TELEGRAM_ENABLED=true -e BACKUP_TELEGRAM_BOT_TOKEN=0:local-mock -e BACKUP_TELEGRAM_CHAT_ID=1 \
  -e BACKUP_TELEGRAM_API_URL=http://mock-telegram:8081 -e BACKUP_ENCRYPTION_KEY="$KEY" -e BACKUP_PART_BYTES=5000000 \
  scheduler php artisan backup:telegram logs

step "what arrived"
curl -fsS "$MOCK/messages" > "$WORK/messages.json"
python3 - "$WORK/messages.json" <<'PY'
import json, sys
docs = [m for m in json.load(open(sys.argv[1])) if "document" in m]
for m in docs:
    d = m["document"]
    print(f"  {d['filename']:55} {d['size']/1048576:6.2f} MB  chat {m['payload']['chat_id']}")
    assert d["filename"].endswith(".enc"), "everything must be encrypted"
    assert d["size"] < 50 * 1024 * 1024, "over the Telegram bot limit"
    assert m["payload"]["chat_id"] == "1"
kinds = {"audit_trail": 0, "goat_db": 0, "goat_photos": 0}
for m in docs:
    for k in kinds:
        if m["document"]["filename"].startswith(k):
            kinds[k] += 1
assert kinds["audit_trail"] == 2 and kinds["goat_db"] == 1 and kinds["goat_photos"] >= 2, kinds
print("  ->", kinds)
PY

step "download, decrypt with the EXISTING docker/backup-crypt.php"
python3 - "$WORK/messages.json" > "$WORK/index.txt" <<'PY'
import json, sys
for m in json.load(open(sys.argv[1])):
    if "document" in m:
        print(m["document"]["index"], m["document"]["filename"])
PY
while read -r index name; do
  curl -fsS "$MOCK/files/$index" -o "$WORK/$name"
  decrypt < "$WORK/$name" > "$WORK/${name%.enc}"
done < "$WORK/index.txt"
for f in "$WORK"/*.zip "$WORK"/*.sql.gz; do echo "  decrypted: $(basename "$f")"; done

step "database: restore into a scratch database and compare every table's row count"
DB=$(dc exec -T db printenv MYSQL_DATABASE | tr -d '\r')
sql -e "DROP DATABASE IF EXISTS backup_verify; CREATE DATABASE backup_verify"
gunzip -c "$WORK"/goat_db_*.sql.gz | sql backup_verify
bad=0
for t in $(sql "$DB" -e "SHOW TABLES"); do
  case "$t" in cache|cache_locks|sessions|jobs|pulse_*) continue ;; esac
  live=$(sql "$DB" -e "SELECT COUNT(*) FROM \`$t\`")
  back=$(sql backup_verify -e "SELECT COUNT(*) FROM \`$t\`")
  [ "$live" = "$back" ] || { echo "  DIFFERENT $t live=$live backup=$back"; bad=1; }
done
[ "$bad" = 0 ] || fail "restored database differs"
echo "  $(sql backup_verify -e 'SHOW TABLES' | wc -l | tr -d ' ') tables restored, row counts equal"

step "photos: every part unzips and the union equals the live files, byte for byte"
mkdir "$WORK/photos"
for z in "$WORK"/goat_photos_*_part*.zip; do unzip -q -o "$z" -d "$WORK/photos"; done
(cd "$WORK/photos" && find . -type f -exec shasum -a 256 {} + | sed 's# \./# #' | sort -k2) > "$WORK/photos.sha"
dc exec -T app sh -c 'cd /app/storage/app/public && find . -type f ! -path "./temp/*" ! -name avatars.zip ! -name ".*" -exec sha256sum {} + | sed "s# \./# #"' | tr -d '\r' | sort -k2 > "$WORK/live.sha"
diff <(awk '{print $1"  "$2}' "$WORK/live.sha") <(awk '{print $1"  "$2}' "$WORK/photos.sha") > /dev/null || fail "photos differ from the live volume"
echo "  $(wc -l < "$WORK/photos.sha" | tr -d ' ') files identical"

step "audit log: the parts rebuild the live log, every line exactly once"
unzip -p "$WORK"/audit_trail-*_part1.zip > "$WORK/audit.rebuilt"
unzip -p "$WORK"/audit_trail-*_part2.zip >> "$WORK/audit.rebuilt"
grep -q "$MARK" "$WORK/audit.rebuilt" || fail "the late event is missing"
[ "$(grep -c "$MARK" "$WORK/audit.rebuilt")" = 1 ] || fail "the late event was sent twice"
unzip -p "$WORK"/audit_trail-*_part2.zip | grep -q "$MARK" || fail "the late event is not in the catch-up part"
unzip -p "$WORK"/audit_trail-*_part1.zip | grep -q "$MARK" && fail "the late event leaked into the first part"
dc exec -T scheduler sh -c "cat /app/storage/logs/audit_trail-\$(date +%F).log" > "$WORK/audit.live"
size=$(wc -c < "$WORK/audit.rebuilt" | tr -d ' ')
head -c "$size" "$WORK/audit.live" | cmp -s - "$WORK/audit.rebuilt" || fail "the parts are not a clean prefix of the live log"
echo "  $(wc -l < "$WORK/audit.rebuilt" | tr -d ' ') lines in 2 parts, no gap, no repeat"

step "clean up and metrics"
left=$(dc exec -T scheduler sh -c 'ls /app/storage/backup-tmp | wc -l' | tr -d '\r ')
[ "$left" = 0 ] || fail "temp files left behind: $left"
echo "  no temp files left"
dc exec -T scheduler php artisan app:export-metrics
dc exec -T scheduler grep -E '^goat_backup_(last_success|last_run_ok)' /app/storage/metrics/goat.prom | sed 's/^/  /'

echo
echo "backup e2e passed"
