#!/usr/bin/env bash
# monitoring/secrets (+ monitoring/.env for prod) and the read-only MySQL user for the exporter
# local: monitoring/scripts/init.sh local
# prod:  TELEGRAM_BOT_TOKEN=... monitoring/scripts/init.sh prod
set -euo pipefail
cd "$(dirname "$0")/../.."

case "${1:-}" in
  local) MAIN=docker-compose.full-local.yml ;;
  prod) MAIN=docker-compose.yml ;;
  *) echo "usage: monitoring/scripts/init.sh local|prod" >&2; exit 2 ;;
esac
MODE=$1
S=monitoring/secrets

umask 077
mkdir -p "$S"
chmod 700 "$S"

rand() { openssl rand -base64 48 | tr -d '/+=\n' | cut -c1-"$1"; }
# the directory is 0700, so 0444 files only reach the non-root users inside the containers
put() { rm -f "$S/$1"; printf '%s' "$2" > "$S/$1"; chmod 444 "$S/$1"; }

if [ ! -s "$S/grafana_admin_password" ]; then
  put grafana_admin_password "$(rand 24)"
  echo "grafana admin password: $S/grafana_admin_password"
fi

if [ "$MODE" = local ]; then
  put telegram_bot_token.local "0:local-mock-token"
else
  [ -z "${TELEGRAM_BOT_TOKEN:-}" ] || put telegram_bot_token "$TELEGRAM_BOT_TOKEN"
  [ -s "$S/telegram_bot_token" ] || { echo "set TELEGRAM_BOT_TOKEN or create $S/telegram_bot_token" >&2; exit 1; }
fi

dc() { docker compose -f "$MAIN" "$@"; }

if [ ! -s "$S/mysql_exporter.cnf" ]; then
  pw=$(rand 32)
  dc exec -T db sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" mysql -uroot" <<SQL
CREATE USER IF NOT EXISTS 'goat_exporter'@'%' IDENTIFIED BY '$pw' WITH MAX_USER_CONNECTIONS 3;
ALTER USER 'goat_exporter'@'%' IDENTIFIED BY '$pw' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'goat_exporter'@'%';
GRANT SELECT ON performance_schema.* TO 'goat_exporter'@'%';
SQL
  put mysql_exporter.cnf "[client]
user=goat_exporter
password=$pw
host=db
port=3306
"
fi

if [ "$MODE" = prod ]; then
  net=$(docker inspect -f '{{range $k, $v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$(dc ps -q app)" | awk '{print $1}')
  vol=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/app/storage/metrics"}}{{.Name}}{{end}}{{end}}' "$(dc ps -q scheduler)")
  [ -n "$net" ] && [ -n "$vol" ] || { echo "start the app stack first (needs the app network and the goat-metrics volume)" >&2; exit 1; }
  printf 'APP_NETWORK=%s\nMETRICS_VOLUME=%s\n' "$net" "$vol" > monitoring/.env
fi

echo "ok ($MODE)"
