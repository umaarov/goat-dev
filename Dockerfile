# Build Frontend
FROM node:20-alpine AS frontend_builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build

# Build Backend
FROM composer:2 AS backend_builder

WORKDIR /app

COPY composer.json composer.lock ./
COPY app ./app
COPY database ./database
RUN composer install --no-dev --ignore-platform-reqs --no-interaction --prefer-dist --optimize-autoloader
FROM dunglas/frankenphp:php8.3-alpine

RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    bcmath \
    redis

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=backend_builder /app/vendor /app/vendor
COPY --from=frontend_builder /app/public/build /app/public/build
COPY . /app
RUN chmod -R 777 /app/storage /app/bootstrap/cache
ENV SERVER_NAME=":80"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
