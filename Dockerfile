FROM node:20-alpine AS frontend_builder
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
#COPY .env .env
#COPY .env ./
ARG VITE_PUSHER_APP_KEY
ARG VITE_PUSHER_APP_CLUSTER
ENV VITE_PUSHER_APP_KEY=${VITE_PUSHER_APP_KEY}
ENV VITE_PUSHER_APP_CLUSTER=${VITE_PUSHER_APP_CLUSTER}

COPY . .
RUN npm run build

FROM composer:2 AS backend_builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev \
    --no-scripts
COPY . .

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
    && apk add --no-cache \
    build-base \
    libwebp-dev \
    libjpeg-turbo-dev \
    gcc \
    musl-dev \
    nodejs \
    npm

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=backend_builder /app/vendor /app/vendor
COPY --from=frontend_builder /app/public/build /app/public/build
COPY . /app
WORKDIR /app
RUN gcc -O3 -o image_processor image_processor_dev/image_processor.c -lwebp -lm \
    && chmod +x image_processor
RUN chmod -R 777 /app/storage /app/bootstrap/cache \
    && rm -f /app/bootstrap/cache/*.php
ENV SERVER_NAME=":80"
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
