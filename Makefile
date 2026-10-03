#export PUID=$(shell id -u)
#export PGID=$(shell id -g)

all: up

up:
	docker-compose up -d --build --remove-orphans
down:
	docker-compose down
stop:
	docker-compose stop
logs:
	docker-compose logs -f $(filter-out $@,$(MAKECMDGOALS))

artisan:
	docker-compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))
composer:
	docker-compose exec app composer $(filter-out $@,$(MAKECMDGOALS))
npm:
	docker-compose exec app npm $(filter-out $@,$(MAKECMDGOALS))
test:
	docker-compose exec app php artisan test

setup: up artisan-migrate artisan-optimize

composer-install:
	docker-compose exec app composer install

artisan-migrate:
	docker-compose exec app php artisan migrate --seed

artisan-optimize:
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache
	docker-compose exec app php artisan view:cache


.PHONY: all up down stop logs artisan composer npm test setup composer-install artisan-migrate artisan-optimize

# local stack (docker-compose.full-local.yml). artisan/composer/tests must run in the container, not on the host
LOCAL = docker compose -f docker-compose.full-local.yml

local-up:
	$(LOCAL) up -d
local-down:
	$(LOCAL) down
local-artisan:
	$(LOCAL) exec app php artisan $(ARGS)
local-test:
	$(LOCAL) --profile tools run --rm test
local-restore:
	./docker/local-restore.sh
local-backup-test:
	./docker/test-backup.sh

# monitoring (docker-compose.monitoring.yml): local runs against the local stack with a mock Telegram
MON_LOCAL = docker compose --env-file .env --env-file monitoring/.env.local -f docker-compose.monitoring.yml -f docker-compose.monitoring.local.yml
MON_PROD = docker compose --env-file .env --env-file monitoring/.env -f docker-compose.monitoring.yml

monitoring-up:
	./monitoring/scripts/init.sh local
	$(MON_LOCAL) up -d
monitoring-down:
	$(MON_LOCAL) down
monitoring-test:
	./monitoring/scripts/test.sh
monitoring-prod-init:
	./monitoring/scripts/init.sh prod
monitoring-prod-up:
	$(MON_PROD) up -d
monitoring-prod-down:
	$(MON_PROD) down

.PHONY: local-up local-down local-artisan local-test local-restore local-backup-test monitoring-up monitoring-down monitoring-test monitoring-prod-init monitoring-prod-up monitoring-prod-down
