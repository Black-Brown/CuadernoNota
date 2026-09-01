FROM php:8.4-fpm-bookworm

ENV PORT=8000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        gettext-base \
        git \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        nginx \
        supervisor \
        unzip \
    && docker-php-ext-install intl opcache pcntl pdo_pgsql zip \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --classmap-authoritative \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . .
RUN cp docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf \
    && cp docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini \
    && cp docker/supervisord.conf /etc/supervisor/supervisord.conf \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
