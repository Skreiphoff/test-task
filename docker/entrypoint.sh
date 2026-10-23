#!/bin/sh
set -e

cd /var/www/html

# Only the app service prepares the project; other services wait for it via healthcheck.
if [ "$1" = "php-fpm" ]; then
    [ -f .env ] || cp .env.example .env
    composer install --no-interaction --prefer-dist
    grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    php artisan migrate --force
fi

exec "$@"
