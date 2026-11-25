#!/usr/bin/env bash
# Chạy cả 4 tiến trình trên máy cá nhân; Ctrl-C để dừng tất cả.
# PHP_CLI_SERVER_WORKERS: artisan serve mặc định đơn luồng, gọi chéo dịch vụ sẽ bị treo nếu thiếu.
cd "$(dirname "$0")" || exit 1
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-8}

mkdir -p logs
for entry in gateway:8000 warranty-service:8001 repair-service:8002 inventory-service:8003; do
  app=${entry%%:*}; port=${entry##*:}
  (cd "$app" && php artisan serve --host=127.0.0.1 --port="$port" >"../logs/$app.log" 2>&1) &
  echo "$app -> http://127.0.0.1:$port"
done

trap 'pkill -P $$; exit 0' INT TERM
wait
