#!/bin/sh
set -eu

# No migrations, seeds, key generation or shared cache clearing at cold start.
: "${APP_KEY:?Configura APP_KEY como secreto del proyecto API.}"
if [ "${CACHE_STORE:-}" != database ] || [ "${SESSION_DRIVER:-}" != database ]; then
    echo "Vercel requiere CACHE_STORE=database y SESSION_DRIVER=database en este despliegue." >&2
    exit 1
fi
export PORT="${PORT:-80}"
case "$PORT" in ''|*[!0-9]*) echo "PORT debe ser numérico." >&2; exit 1;; esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT fuera de rango." >&2
    exit 1
fi

mkdir -p /tmp/laravel-storage/framework/cache/data /tmp/laravel-storage/framework/sessions \
    /tmp/laravel-storage/framework/views /tmp/laravel-storage/logs \
    /tmp/nginx/client /tmp/nginx/fastcgi
chown -R www-data:www-data /tmp/laravel-storage /tmp/nginx
envsubst '${PORT}' < docker/vercel/nginx.conf.template > /tmp/nginx.conf
nginx -t -c /tmp/nginx.conf
exec "$@"
