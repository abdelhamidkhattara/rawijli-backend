#!/bin/sh
set -e

cd /var/www/html

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo ">> Running database migrations"
    php artisan migrate --force
fi

echo ">> Linking public storage"
php artisan storage:link || true

echo ">> Publishing Filament assets"
php artisan filament:upgrade || true

echo ">> Caching configuration"
php artisan config:cache

echo ">> Starting application"
exec "$@"
