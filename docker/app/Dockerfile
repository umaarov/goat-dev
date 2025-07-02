# ---- Stage 1: Frontend Asset Builder ----
FROM node:20 AS frontend-builder
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends python3 git cmake bash
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
      -s "EXPORTED_FUNCTIONS=['_createComplexWeaverGeometry', '_malloc', '_free']" \
      -s "EXPORTED_RUNTIME_METHODS=['cwrap', 'getValue', 'wasmMemory']"

RUN npm run build

# ---- Stage 2: Composer Dependency Builder ----
FROM composer:2.5 as composer-builder
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --optimize-autoloader


# ---- Stage 3: The Hardened Production Image ----
FROM dunglas/frankenphp:1.1-php8.3-alpine

ARG PUID=1000
ARG PGID=1000

WORKDIR /app

RUN addgroup -g ${PGID} goat && \
    adduser -u ${PUID} -G goat -s /bin/sh -D goat

RUN install-php-extensions \
    pdo_mysql \
    redis \
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

RUN chown -R goat:goat /app
USER goat

EXPOSE 80 443 443/udp

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
