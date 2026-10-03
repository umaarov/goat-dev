# Monitoring

Prometheus (metrics) + Loki (logs) + Alloy (collector) + Alertmanager + Grafana, alerts to Telegram.
Own compose project (`docker-compose.monitoring.yml`) that joins the app network; nothing in the app stack depends on it.

## What it watches

| Source | How | Examples |
|---|---|---|
| Host | node-exporter | CPU, RAM, disk (and disk filling within 24h), OOM kills, reboot |
| Containers | cAdvisor (Linux host) | memory vs limit, OOM, restart loops, missing services |
| MySQL / Redis | exporters | down, connections, slow queries, evictions, snapshot failures |
| Site | blackbox probes | public URL + origin health, latency, TLS expiry |
| Web server | Caddy/FrankenPHP metrics | requests, status codes, latency, PHP threads |
| App | `app:export-metrics` (scheduler, every minute) | queue sizes, worker heartbeat, failed jobs, users/posts/comments, sessions |
| Logs | Alloy -> Loki | audit trail, laravel.log, Caddy access log (secrets redacted), all container logs |

Log alerts (Loki ruler): token theft, login failure bursts, forged verification links, unauthorized edits,
IPs hammering 401/403/429/404, 5xx bursts, CRITICAL/ERROR in laravel.log, moderation/DeepSeek/Groq failures,
failed social posting, AI picture failures. Metric alerts: `monitoring/prometheus/rules/*.yml`.

Critical alerts notify with sound, warnings are silent. Resolved messages are sent too. `Watchdog` always fires and goes nowhere.

## Deploy (prod)

```
# 1. app stack up first (needs the app network and the goat-metrics volume)
docker compose up -d --build

# 2. secrets + exporter user (creates monitoring/secrets, monitoring/.env)
TELEGRAM_BOT_TOKEN=<token> make monitoring-prod-init   # = monitoring/scripts/init.sh prod

# 3. start
make monitoring-prod-up

# 4. one test message to the real channel
monitoring/scripts/telegram-test.sh
```

Grafana listens on 127.0.0.1:3000 only. Open it through a tunnel:
`ssh -L 3000:127.0.0.1:3000 user@server` then http://localhost:3000, user `admin`, password in `monitoring/secrets/grafana_admin_password`.
Prometheus 9090 and Alertmanager 9093 are bound to localhost the same way.

The worker command must list every queue (`--queue=default,scoring`). Jobs sent to a queue nobody reads wait forever.

## Local

```
make local-up && make monitoring-up      # alerts go to a mock Telegram, never to the real bot
make monitoring-test                     # static checks + real alerts end to end (~6 min)
```
Local uses `monitoring/secrets/telegram_bot_token.local` (a dummy). The real token is only mounted by the prod file.
cAdvisor does not work inside the Docker Desktop VM; on a Linux host start it with `--profile linux-only`.

## Operations

- Retention: metrics 30 days (20 GB cap), logs 31 days, audit trail 90 days.
- A silent server cannot alert about itself. Add a dead-man's switch: a free healthchecks.io check, then in
  `alertmanager.yml` route `Watchdog` to a `webhook_configs` receiver with that URL (`url_file`). It pages you when the pings stop.
- Add an alert: edit `prometheus/rules/*.yml` or `loki/rules/fake/*.yml`, `make monitoring-test` (static), restart the service.
- Rotate the bot token: new file in `monitoring/secrets/telegram_bot_token`, `docker compose ... restart alertmanager`.
- Secrets live in `monitoring/secrets` (gitignored, directory 0700, files 0444 so the non-root containers can read them).
- The Caddy access log never contains cookies, Authorization, `Set-Cookie`, or tokens in URLs (reset, verify, unsubscribe, OAuth `code`/`state`).
