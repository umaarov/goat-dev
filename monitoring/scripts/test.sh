#!/usr/bin/env bash
# monitoring/scripts/test.sh [static|e2e]   (default: both)
# static: every config validates. e2e: real alerts travel log/metric -> Alertmanager -> mock Telegram (local stack only)
set -euo pipefail
cd "$(dirname "$0")/../.."

PROM_IMG=prom/prometheus:v3.15.0
AM_IMG=prom/alertmanager:v0.34.1
LOKI_IMG=grafana/loki:3.7.8
ALLOY_IMG=grafana/alloy:v1.20.1
SHELLCHECK_IMG=koalaman/shellcheck:stable

ENVF=()
[ -f .env ] && ENVF=(--env-file .env)
mon() { docker compose ${ENVF[@]+"${ENVF[@]}"} --env-file monitoring/.env.local -f docker-compose.monitoring.yml -f docker-compose.monitoring.local.yml "$@"; }
app() { docker compose -f docker-compose.full-local.yml "$@"; }
step() { printf '\n== %s\n' "$*"; }
fail() { echo "FAIL: $*" >&2; exit 1; }

static() {
  step "prometheus config + rules"
  docker run --rm --entrypoint promtool -v "$PWD/monitoring/prometheus:/etc/prometheus:ro" "$PROM_IMG" check config /etc/prometheus/prometheus.yml
  step "alertmanager configs (prod and local)"
  for f in alertmanager.yml alertmanager.local.yml; do
    docker run --rm --entrypoint amtool -v "$PWD/monitoring/alertmanager:/etc/alertmanager:ro" "$AM_IMG" check-config "/etc/alertmanager/$f"
  done
  step "prod and local alertmanager differ only in the Telegram target"
  diff <(sed -E '/api_url|chat_id|^# |message:/d' monitoring/alertmanager/alertmanager.yml | grep -v 'default api_url') \
       <(sed -E '/api_url|chat_id|^# |message:/d' monitoring/alertmanager/alertmanager.local.yml | grep -v 'default api_url') \
    || fail "routing differs between prod and local"
  step "warnings are silent, criticals make a sound"
  python3 - <<'PY'
import re
for f in ("monitoring/alertmanager/alertmanager.yml", "monitoring/alertmanager/alertmanager.local.yml"):
    s = open(f).read()
    silent = re.search(r"name: telegram\n.*?disable_notifications: (\w+)", s, re.S).group(1)
    loud = re.search(r"name: telegram-critical\n.*?disable_notifications: (\w+)", s, re.S).group(1)
    assert (silent, loud) == ("true", "false"), (f, silent, loud)
print("ok")
PY
  step "loki config"
  docker run --rm -v "$PWD/monitoring/loki:/etc/loki:ro" "$LOKI_IMG" -config.file=/etc/loki/loki.yml -verify-config
  step "alloy config"
  docker run --rm -v "$PWD/monitoring/alloy:/etc/alloy:ro" "$ALLOY_IMG" validate /etc/alloy/config.alloy
  step "compose files"
  mon config -q
  docker compose ${ENVF[@]+"${ENVF[@]}"} --env-file monitoring/.env.local -f docker-compose.monitoring.yml config -q
  step "no secret in the repo tree"
  if git ls-files monitoring | grep -E 'secrets/(telegram|grafana|mysql)'; then fail "a secret is tracked by git"; fi
  if grep -rEn --exclude-dir=secrets '[0-9]{8,}:AA[A-Za-z0-9_-]{30,}' monitoring docker-compose.monitoring*.yml Makefile; then fail "a bot token is in the tree"; fi
  step "shellcheck"
  docker run --rm -v "$PWD:/mnt" -w /mnt "$SHELLCHECK_IMG" monitoring/scripts/*.sh
  echo "static checks passed"
}

messages() { docker exec goat-monitoring-mock-telegram-1 python -c "import urllib.request;print(urllib.request.urlopen('http://127.0.0.1:8081/messages').read().decode())"; }

# wait_for <description> <python predicate on the list of payload texts> <seconds>
wait_for() {
  local what=$1 expr=$2 timeout=$3 start=$SECONDS
  while [ $((SECONDS - start)) -lt "$timeout" ]; do
    if messages | python3 -c "import sys,json; t=[m['payload'].get('text','') for m in json.load(sys.stdin)]; sys.exit(0 if ($expr) else 1)"; then
      echo "ok   $what ($((SECONDS - start))s)"
      return 0
    fi
    sleep 5
  done
  messages | python3 -c "import sys,json; [print('---\n'+m['payload'].get('text','')) for m in json.load(sys.stdin)]" || true
  fail "$what not seen within ${timeout}s"
}

e2e() {
  step "stack is up"
  curl -fsS -o /dev/null http://127.0.0.1:8000/up || fail "local app is not running (make local-up)"
  curl -fsS -o /dev/null http://127.0.0.1:8081/ || fail "monitoring is not running (make monitoring-up)"

  step "clean slate"
  # leftovers from an earlier run would be suppressed by Alertmanager (repeat_interval) and fail the test
  local start=$SECONDS
  until [ "$(docker exec goat-monitoring-mock-telegram-1 python -c "import urllib.request,json;print(len([a for a in json.loads(urllib.request.urlopen('http://alertmanager:9093/api/v2/alerts').read()) if a['labels']['alertname']!='Watchdog']))")" = 0 ]; do
    [ $((SECONDS - start)) -lt 480 ] || fail "alerts from an earlier run did not clear"
    sleep 10
  done
  mon rm -sf alertmanager >/dev/null 2>&1
  docker volume rm goat-monitoring_alertmanager-data >/dev/null
  mon up -d alertmanager >/dev/null 2>&1
  sleep 15
  docker exec goat-monitoring-mock-telegram-1 python -c "import urllib.request;urllib.request.urlopen('http://127.0.0.1:8081/reset')"

  step "synthetic security event + outage"
  log=storage/logs/audit_trail-$(date +%F).log
  offset=$(date +%z | sed -E 's/(..)$/:\1/')
  echo "[$(date '+%F %T') $offset] local.WARNING: [AUTH] [TOKEN_THEFT] Revoked token used outside grace period. (synthetic monitoring test) {\"user_id\":0}" >> "$log"
  app stop app >/dev/null 2>&1
  trap 'app start app >/dev/null 2>&1 || true' EXIT

  wait_for "TokenTheftDetected reaches Telegram (log rule -> Loki ruler -> Alertmanager)" "any('TokenTheftDetected' in x and 'LOCAL TEST' in x for x in t)" 300
  wait_for "SiteDown reaches Telegram (probe -> Prometheus rule -> Alertmanager)" "any('SiteDown' in x for x in t)" 360

  step "recovery"
  app start app >/dev/null 2>&1
  trap - EXIT
  wait_for "SiteDown resolved message" "any('SiteDown' in x and 'resolved' in x for x in t)" 480

  step "nothing crashed"
  for c in alertmanager prometheus loki alloy grafana mock-telegram; do
    restarts=$(docker inspect -f '{{.RestartCount}}' "goat-monitoring-$c-1")
    [ "$restarts" = 0 ] || fail "$c restarted $restarts time(s)"
  done
  echo "ok   no monitoring container restarted"

  step "what was (not) sent"
  messages | python3 -c "
import sys, json
m = json.load(sys.stdin)
texts = [x['payload'].get('text', '') for x in m]
assert not any('Watchdog' in t for t in texts), 'Watchdog must never reach Telegram'
assert all(x['payload'].get('parse_mode') == 'HTML' for x in m), 'messages must be HTML'
assert all(str(x['payload'].get('chat_id')) == '1' for x in m), 'local alerts must use the dummy chat'
crit = [x for x in m if 'TokenTheftDetected' in x['payload'].get('text', '')]
assert crit and all(str(x['payload'].get('disable_notification', 'false')).lower() != 'true' for x in crit), 'critical alerts must notify with sound'
print(len(m), 'messages, no Watchdog, all HTML, dummy chat only, critical is loud')
print('--- sample ---'); print(crit[0]['payload']['text'])
"
  echo "e2e passed"
}

case "${1:-all}" in
  static) static ;;
  e2e) e2e ;;
  all) static; e2e ;;
  *) echo "usage: monitoring/scripts/test.sh [static|e2e]" >&2; exit 2 ;;
esac
