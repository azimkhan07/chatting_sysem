# syntax=docker/dockerfile:1.7

# Runtime for the amteCHAT admin console API.
#
# Deliberately not the same image as backend/: the console is a separate app
# with a separate database, and sharing a base would make it too easy to ship
# this service with the main app's dependencies - or its credentials - by
# accident. Same Debian base as backend/ only so the two build alike; the
# extension list is this app's own and much shorter.
FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

# Composer from the official image; no network access is needed at runtime.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv

COPY admin-backend/composer.json admin-backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist \
    || composer install --no-scripts --no-interaction --no-progress --prefer-dist

COPY admin-backend .
# The console's own database file. Created here rather than mounted in so a
# fresh container has something for the `admin` connection to open; migrations
# still have to run before it holds anything useful.
RUN mkdir -p database && touch database/admin.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 9000

CMD ["php-fpm"]
