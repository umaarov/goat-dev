# ---- Stage 1: Frontend Asset Builder ----
FROM node:20 AS frontend-builder
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 git cmake bash \
    && rm -rf /var/lib/apt/lists/*

COPY package.json package-lock.json ./
RUN npm install

COPY . .
RUN git clone https://github.com/emscripten-core/emsdk.git && \
    cd emsdk && \
    ./emsdk install latest && \
    ./emsdk activate latest && \
    . ./emsdk_env.sh && \
    cd .. && \
    emcc cpp/geometry_optimizer.cpp \
      -o public/assets/wasm/geometry_optimizer.js \
      -O3 \
      -s WASM=1 \
      -s MODULARIZE=1 \
      -s EXPORT_ES6=1 \
      -s "EXPORTED_FUNCTIONS=['_createComplexWeaverGeometry','_malloc','_free']" \
      -s "EXPORTED_RUNTIME_METHODS=['cwrap','getValue','wasmMemory']"

RUN npm run build

# ---- Stage 2: Composer / PHP Dependency Builder ----
FROM dunglas/frankenphp:1.1-php8.3-alpine AS composer-builder
WORKDIR /app

RUN apk add --no-cache \
        libpng libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        zip unzip bash git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && install-php-extensions \
        pdo_mysql \
        redis \
        exif \
        bcmath \
        sockets \
        fileinfo \
        opcache \
    && rm -rf /var/cache/apk/*
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader
COPY . .

# ---- Stage 3: Final Production Image ----
FROM dunglas/frankenphp:1.1-php8.3-alpine
ARG PUID=1000
ARG PGID=1000

WORKDIR /app

RUN addgroup -g ${PGID} goat && \
    adduser -u ${PUID} -G goat -s /bin/sh -D goat
RUN apk add --no-cache nss-tools
RUN apk update \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS autoconf \
    && pecl channel-update pecl.php.net \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && install-php-extensions \
        pdo_mysql \
        gd \
        exif \
        bcmath \
        sockets \
        fileinfo \
        opcache


COPY --from=composer-builder /app/vendor /app/vendor
COPY --from=frontend-builder /app/public/build /app/public/build
COPY --from=frontend-builder /app/public/assets/wasm /app/public/assets/wasm
COPY . .
RUN cp /usr/local/bin/frankenphp /app/vendor/bin/frankenphp-worker \
    && php artisan package:discover --ansi \
    && php artisan octane:install --server=frankenphp

RUN chown -R goat:goat /app /data /config
USER goat
EXPOSE 80 443 443/udp
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
