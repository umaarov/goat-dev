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
