#!/usr/bin/env bash
# deploy the committed HEAD: deploy/deploy.sh user@host   (env: DEPLOY_DIR, DEPLOY_SSH_OPTS, SCHEDULER=1)
set -euo pipefail
HOST=${1:?usage: deploy.sh user@host}
DIR=${DEPLOY_DIR:-/opt/goat}
SSH_OPTS=${DEPLOY_SSH_OPTS:-}
ssh_() { ssh $SSH_OPTS "$HOST" "$@"; }

cd "$(git rev-parse --show-toplevel)"
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT

git archive HEAD | tar -x -C "$STAGE"
mkdir -p "$STAGE/resources/prompts" && cp -R resources/prompts/. "$STAGE/resources/prompts/"
mkdir -p "$STAGE/goat-search" && rsync -a --exclude vendor --exclude .git goat-search/ "$STAGE/goat-search/"

ssh_ "mkdir -p $DIR/storage/logs"
rsync -az --delete -e "ssh $SSH_OPTS" \
    --exclude '/.env' --exclude '/storage/logs/' --exclude '/docker/certs/' --exclude '/backup/' \
    "$STAGE/" "$HOST:$DIR/"

ssh_ "cd $DIR && test -f .env && docker compose build && docker compose up -d --remove-orphans db redis app worker goat-search"
ssh_ "cd $DIR && until [ \"\$(docker compose ps app --format '{{.Health}}')\" = healthy ]; do sleep 3; done && docker compose exec -T app php artisan migrate --force"
[ "${SCHEDULER:-0}" = 1 ] && ssh_ "cd $DIR && docker compose up -d scheduler"
ssh_ "cd $DIR && docker compose ps --format 'table {{.Service}}\t{{.Status}}'"
