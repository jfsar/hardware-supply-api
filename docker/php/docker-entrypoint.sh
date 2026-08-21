#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    php artisan key:generate --force
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress --prefer-dist
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
fi

exec "$@"
