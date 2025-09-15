FROM php:8.4.13RC1-cli-alpine

ARG PUID=1000
ARG PGID=1000

WORKDIR /app

RUN addgroup -g ${PGID} goat && \
    adduser -u ${PUID} -G goat -s /bin/sh -D goat

RUN apk add --no-cache supervisor \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && docker-php-ext-install \
        pdo_mysql \
        exif \
        bcmath \
        pcntl


COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

COPY docker/queue/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

COPY . .

RUN composer install --no-dev --no-interaction --optimize-autoloader

RUN chown -R goat:goat /app
USER goat

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisor.conf"]
