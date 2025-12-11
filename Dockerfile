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
RUN #composer install --no-dev --ignore-platform-reqs --no-interaction --prefer-dist --optimize-autoloader
RUN composer install --ignore-platform-reqs --no-interaction --prefer-dist --optimize-autoloader --no-scripts
FROM dunglas/frankenphp:php8.3-alpine

RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    bcmath \
    redis \

RUN apk add --no-cache \
    build-base \
    libwebp-dev \
    libjpeg-turbo-dev \
    gcc \
    musl-dev

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=backend_builder /app/vendor /app/vendor
COPY --from=frontend_builder /app/public/build /app/public/build
COPY . /app

WORKDIR /app
RUN gcc -O3 -o image_processor image_processor_dev/image_processor.c -lwebp -lm \
    && chmod +x image_processor

RUN chmod -R 777 /app/storage /app/bootstrap/cache
ENV SERVER_NAME=":80"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN rm -f /app/bootstrap/cache/*.php

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
