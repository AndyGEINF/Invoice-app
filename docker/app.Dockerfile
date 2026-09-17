# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# INVOICE · imagen web (PHP 8.4-FPM + Nginx)
#
# La aplicación no tiene login: publica este contenedor solo en localhost o en
# una red privada, o detrás de un proxy con contraseña (ver docs/deploy.md).
# ---------------------------------------------------------------------------

# --- 1. Dependencias PHP ----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist \
    --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-intl --ignore-platform-req=ext-pdo_pgsql
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# --- 2. Assets del frontend -------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- 3. Base PHP con las extensiones que exige la aplicación ----------------
FROM php:8.4-fpm-bookworm AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_pgsql pgsql zip gd opcache \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-invoice.ini
WORKDIR /var/www/html

# --- 4. Imagen final web ----------------------------------------------------
FROM php-base AS app
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/conf.d/invoice.conf
COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN chmod +x /usr/local/bin/app-entrypoint \
    && rm -f .env \
    && mkdir -p storage/app/private/documents storage/app/public/logos \
        storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
ENTRYPOINT ["app-entrypoint"]
