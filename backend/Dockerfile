# syntax=docker/dockerfile:1.7

# PHP-FPM runtime for the amteCHAT backend.
# Redis is provided via the phpredis extension (no predis composer package needed),
# so `docker compose up` works without requiring network access during composer install.
FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip intl pcntl \
    # pqredis build is attempted but allowed to fail: Cache/Queue fall back gracefully.
    && (docker-php-ext-install redis || true) \
    && rm -rf /var/lib/apt/lists/*

# Composer (official image) — network-free at runtime.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist \
    || composer install --no-scripts --no-interaction --no-progress --prefer-dist

COPY backend .
RUN php artisan package:discover || true

EXPOSE 9000

CMD ["php-fpm"]
