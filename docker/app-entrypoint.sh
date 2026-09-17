#!/bin/sh
set -e

cd /var/www/html

# Directorios de almacenamiento (el volumen puede llegar vacío).
mkdir -p storage/app/private/documents storage/app/public/logos \
    storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage

# Migraciones, emisor vacío y series por defecto (idempotente).
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\InstallSeeder --force || true
php artisan storage:link || true
php artisan optimize

php-fpm -D
exec nginx -g 'daemon off;'
