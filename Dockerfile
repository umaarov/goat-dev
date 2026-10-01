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
    --ignore-platform-req=ext-* \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev \
    --no-scripts
COPY . .

# the native image processor is compiled here so the runtime image carries no compiler or -dev headers
FROM dunglas/frankenphp:php8.4-alpine AS c_builder
RUN apk add --no-cache build-base libwebp-dev
WORKDIR /src
COPY image_processor_dev/ ./
RUN gcc -O3 -o image_processor image_processor.c -lwebp -lm

FROM dunglas/frankenphp:php8.4-alpine AS runtime
RUN apk add --no-cache \
    libwebp \
    libjpeg-turbo \
    libpng \
    freetype \
    curl

RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    pcntl \
    bcmath \
    redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=backend_builder /app/vendor /app/vendor
COPY --from=frontend_builder /app/public/build /app/public/build
COPY . /app
COPY --from=c_builder --chmod=755 /src/image_processor /app/image_processor
WORKDIR /app
RUN chmod -R 777 /app/storage /app/bootstrap/cache \
    && rm -f /app/bootstrap/cache/*.php \
    && ln -sfn /app/storage/app/public /app/public/storage
ENV SERVER_NAME=":80"
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# docker-compose.dev.yml: the entrypoint runs `npm install && npm run build` outside production
FROM runtime AS dev
RUN apk add --no-cache nodejs npm

# last stage = default target = what production builds
FROM runtime AS production
