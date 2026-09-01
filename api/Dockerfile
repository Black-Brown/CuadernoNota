FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install intl pcntl pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
# `php artisan serve` deliberately strips most env vars from its child process (except a small
# allowlist) so it can hot-reload when .env changes -- that silently shadows real DB_HOST/secrets
# injected by Docker Compose or a host like Render/Railway with whatever is in .env (or .env.example
# if no .env ships). Run the built-in server directly with Laravel's own router instead, which
# inherits the real process environment like any other PHP process.
# $PORT is honored because Render/Railway assign it dynamically; 8000 is the local/Compose default.
CMD ["sh", "-c", "cd public && exec php -S 0.0.0.0:${PORT:-8000} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
