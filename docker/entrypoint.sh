#!/bin/sh
set -e

echo "Linking storage..."
php artisan storage:link || true

if echo "$@" | grep -q "artisan"; then
    echo "Worker/Scheduler detected."
else
    if [ "$APP_ENV" != "production" ]; then
        if [ ! -f "node_modules/.bin/vite" ]; then
            npm install
        fi
        npm run build
    else
        echo "Production: Skipping NPM build."
    fi

    echo "Caching configuration..."
    php artisan optimize:clear
    php artisan config:cache
    php artisan event:cache
    php artisan route:cache
    php artisan view:cache
fi

# shellcheck disable=SC2145
echo "✅ Container Starting: $@"
exec "$@"
