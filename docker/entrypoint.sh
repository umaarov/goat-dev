#!/bin/sh
set -e

echo "Linking storage..."
php artisan storage:link || true

if [ ! -d "vendor" ] || [ -z "$(ls -A vendor)" ]; then
    echo "Vendor folder missing. Installing Composer dependencies..."
    composer install --optimize-autoloader --no-dev --no-interaction
fi

if [ ! -d "node_modules" ]; then
    echo "Node modules missing. Installing NPM dependencies..."
    npm install
fi

echo "Building frontend assets..."
npm run build

if [ "$APP_ENV" = "production" ]; then
    echo "Caching configurations..."
    php artisan config:clear
    php artisan cache:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

echo "Application Starting..."
exec "$@"
