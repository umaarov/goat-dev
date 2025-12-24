#!/bin/sh
set -e

echo "Linking storage..."
php artisan storage:link || true

if echo "$@" | grep -q "artisan"; then
    echo "Worker/Scheduler detected. Skipping Node/Vite checks."
else
    if [ ! -f "node_modules/.bin/vite" ]; then
        echo "Vite missing. Installing dependencies..."
        npm install
    fi

    echo "Building frontend assets..."
    npm run build

    if [ "$APP_ENV" = "production" ]; then
        echo "Caching configuration..."
        php artisan config:clear
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi
fi

# shellcheck disable=SC2145
echo "✅ Container Starting: $@"
exec "$@"
