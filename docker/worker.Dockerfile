# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# INVOICE · worker de cola (PHP 8.4-CLI + Chromium + Node para Browsershot)
#
# Ejecuta `php artisan queue:work`: renderizado de PDFs, envío de emails y
# validación VIES. Nunca atiende peticiones HTTP.
# ---------------------------------------------------------------------------

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist \
    --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-intl --ignore-platform-req=ext-pdo_pgsql
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM php:8.4-cli-bookworm AS worker
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
        chromium fonts-dejavu-core fonts-liberation nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_pgsql pgsql zip gd opcache pcntl \
    && apt-get purge -y --auto-remove libicu-dev libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-invoice.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
# Browsershot necesita el paquete puppeteer; se usa el Chromium del sistema.
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium \
    BROWSERSHOT_NODE_BINARY=/usr/bin/node \
    BROWSERSHOT_NPM_BINARY=/usr/bin/npm
RUN rm -f .env \
    && npm install --omit=dev --no-save puppeteer \
    && mkdir -p storage/app/private/documents storage/app/public/logos \
        storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache node_modules

USER www-data
CMD ["php", "artisan", "queue:work", "--tries=3", "--backoff=30", "--max-time=3600"]
