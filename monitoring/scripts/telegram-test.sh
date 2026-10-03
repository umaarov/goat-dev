#!/usr/bin/env bash
# sends ONE message to the real alert channel: run it yourself on the server after deploy
# monitoring/scripts/telegram-test.sh
set -euo pipefail
cd "$(dirname "$0")/../.."

TOKEN_FILE=monitoring/secrets/telegram_bot_token
CHAT_ID=${TELEGRAM_ALERT_CHAT_ID:-$(sed -nE 's/^[[:space:]]*chat_id:[[:space:]]*(-?[0-9]+).*/\1/p' monitoring/alertmanager/alertmanager.yml | head -1)}
[ -s "$TOKEN_FILE" ] || { echo "missing $TOKEN_FILE (TELEGRAM_BOT_TOKEN=... monitoring/scripts/init.sh prod)" >&2; exit 1; }
[ -n "$CHAT_ID" ] || { echo "chat id not found" >&2; exit 1; }

echo "sending to chat $CHAT_ID"
curl -fsS --max-time 15 "https://api.telegram.org/bot$(cat "$TOKEN_FILE")/sendMessage" \
  --data-urlencode "chat_id=$CHAT_ID" \
  --data-urlencode "parse_mode=HTML" \
  --data-urlencode "text=✅ <b>Monitoring test</b>
Alerts from $(hostname) reach this channel." | python3 -c "import sys,json; r=json.load(sys.stdin); print('sent' if r.get('ok') else r)"
