#!/bin/sh
set -e

if [ "$APP_ENV" = "production" ]; then
    echo "Production. Caching configurations..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# php artisan migrate --force

echo "Starting..."

exec "$@"
