#!/bin/sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# A real APP_KEY env var (e.g. set on Render/Railway) always wins over .env and makes
# `artisan key:generate` refuse to run, so only generate one when APP_KEY is unset everywhere.
if [ -z "${APP_KEY:-}" ] && ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

attempt=0
until php artisan migrate:status >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "No fue posible conectar con PostgreSQL." >&2
        exit 1
    fi
    sleep 2
done

php artisan migrate --force
php artisan optimize:clear

exec "$@"
