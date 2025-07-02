FROM composer:2.5 as composer-base

WORKDIR /app

COPY composer.json composer.lock ./
COPY database/ database/

RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && composer require laravel/octane
