#!/bin/sh
set -e

echo "Linking storage..."
php artisan storage:link || true

if [ ! -f "node_modules/.bin/vite" ]; then
    echo "Vite binary missing. Installing NPM dependencies..."
    npm install
fi

if echo "$@" | grep -q "frankenphp"; then
    echo "Building frontend assets (Web Server)..."
    npm run build
else
    echo "Skipping asset build (Worker/Scheduler detected)."
fi

if [ "$APP_ENV" = "production" ] && echo "$@" | grep -q "frankenphp"; then
    echo "Production mode: Caching configurations..."
    php artisan config:clear
    php artisan cache:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

echo "Container Starting..."
exec "$@"
