#!/bin/sh
# Khởi động một dịch vụ Laravel: chuẩn bị .env, vendor, migrate (+ seed nếu SEED=1), rồi chạy php-fpm (nginx đứng trước).
set -e
cd /app

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi
[ -d vendor ] || composer install --no-interaction --prefer-dist

php artisan migrate --force
[ -z "$SEED" ] || php artisan db:seed --force

exec php-fpm
