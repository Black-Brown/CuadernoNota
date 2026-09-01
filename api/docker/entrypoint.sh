#!/bin/sh
set -eu

if [ ! -f .env ] && [ "${APP_ENV:-local}" != "production" ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        composer install --classmap-authoritative --no-dev --no-interaction --prefer-dist
    else
        composer install --no-interaction --prefer-dist
    fi
fi

# A real APP_KEY env var (e.g. set on Render/Railway) always wins over .env and makes
# `artisan key:generate` refuse to run, so only generate one when APP_KEY is unset everywhere.
if [ -z "${APP_KEY:-}" ] && { [ ! -f .env ] || ! grep -Eq '^APP_KEY=base64:.+' .env; }; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "APP_KEY es obligatorio en producción. Configúralo como variable secreta." >&2
        exit 1
    fi
    php artisan key:generate --force
fi

attempt=0
database_probe='require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); Illuminate\Support\Facades\DB::connection()->getPdo();'

until php -r "$database_probe" >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "No fue posible conectar con PostgreSQL. Diagnóstico de Laravel:" >&2
        php -r "$database_probe" || true
        exit 1
    fi
    echo "Esperando PostgreSQL (${attempt}/30)..."
    sleep 2
done

php artisan migrate --force
php artisan optimize:clear

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
fi

export PORT="${PORT:-8000}"
envsubst '${PORT}' < docker/nginx.conf.template > /etc/nginx/conf.d/default.conf
nginx -t

exec "$@"
