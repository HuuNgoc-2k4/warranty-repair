#!/usr/bin/env bash
# Hàm dùng chung cho scripts/e2e.sh và scripts/concurrency.sh
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KEY="${SERVICE_KEY:-wr-dev-key}"
WARRANTY="${WARRANTY_URL:-http://127.0.0.1:8001}"
REPAIR="${REPAIR_URL:-http://127.0.0.1:8002}"
INVENTORY="${INVENTORY_URL:-http://127.0.0.1:8003}"
FAILS=0
BODY=""
CODE=""

# call METHOD URL [json] [header...]: gán BODY và CODE
call() {
  local method=$1 url=$2 data=${3:-} out
  shift 3 2>/dev/null || shift $#
  local args=(-s -m 40 -w '\n%{http_code}' -X "$method" "$url" -H "X-Service-Key: $KEY" -H 'Accept: application/json' -H 'Content-Type: application/json')
  for h in "$@"; do args+=(-H "$h"); done
  [ -n "$data" ] && args+=(-d "$data")
  out=$(curl "${args[@]}")
  CODE=${out##*$'\n'}
  BODY=${out%$'\n'*}
}

# jget 'data.status': đọc field từ BODY
jget() {
  python3 -c 'import json,sys
d=json.loads(sys.argv[1])
for k in sys.argv[2].split("."):
    d=d[int(k)] if isinstance(d,list) else d.get(k)
print("" if d is None else str(d).lower() if isinstance(d,bool) else d)' "$BODY" "$1"
}

# available_of PART_ID: tồn kho hiện tại
available_of() {
  call GET "$INVENTORY/parts"
  python3 -c 'import json,sys
for p in json.loads(sys.argv[1])["data"]:
    if p["id"]==int(sys.argv[2]): print(p["available"])' "$BODY" "$1"
}

# check "mô tả" thực_tế mong_đợi
check() {
  if [ "$2" == "$3" ]; then echo "  PASS  $1"; else echo "  FAIL  $1 (được '$2', cần '$3')"; FAILS=$((FAILS+1)); fi
}

# Xóa dữ liệu của Repair và Inventory (phiếu và lượt giữ phải đi cùng nhau vì ref = T{id}).
# DOCKER=1: chạy lệnh trong container thay vì trên máy.
reset_data() {
  [ -n "${SKIP_RESET:-}" ] && return
  if [ -n "${DOCKER:-}" ]; then
    (cd "$ROOT" && docker compose exec -T inventory-app php artisan migrate:fresh --seed --force >/dev/null \
      && docker compose exec -T repair-app php artisan migrate:fresh --force >/dev/null)
    return
  fi
  (cd "$ROOT/inventory-service" && php artisan migrate:fresh --seed --force >/dev/null)
  (cd "$ROOT/repair-service" && php artisan migrate:fresh --force >/dev/null)
}

# new_ticket KEY SERIAL PART [header...]: tạo phiếu qua Repair
new_ticket() {
  local key=$1 serial=$2 part=$3
  shift 3
  call POST "$REPAIR/tickets" "{\"serial\":\"$serial\",\"customer\":\"Nguyen Van A\",\"phone\":\"0901234567\",\"issue\":\"Loi test\",\"part_id\":$part,\"qty\":1}" "Idempotency-Key: $key" "$@"
}

finish() {
  echo
  if [ "$FAILS" -eq 0 ]; then echo "TẤT CẢ ĐẠT"; else echo "$FAILS KIỂM TRA LỖI"; exit 1; fi
}
