#!/bin/sh
# Khởi động một dịch vụ Laravel: chuẩn bị .env, vendor, migrate (+ seed nếu SEED=1), rồi serve.
set -e
cd /app

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi
[ -d vendor ] || composer install --no-interaction --prefer-dist

php artisan migrate --force
[ -z "$SEED" ] || php artisan db:seed --force

# Không dùng `artisan serve`: nó không truyền env của container (DB_HOST, URL dịch vụ) xuống tiến trình con
cd public
exec php -S "0.0.0.0:$PORT" /app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
