#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ "${CLEAR_LARAVEL_CACHE:-false}" = "true" ]; then
    php artisan optimize:clear || true
fi

if [ "${GENERATE_APP_KEY:-false}" = "true" ] && [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force || true
fi

if [ -z "${APP_KEY:-}" ] && [ -f .env ]; then
    APP_KEY_FROM_FILE=$(grep '^APP_KEY=' .env | tail -n 1 | cut -d '=' -f 2- || true)

    if [ -n "${APP_KEY_FROM_FILE:-}" ]; then
        export APP_KEY="$APP_KEY_FROM_FILE"
    fi
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"