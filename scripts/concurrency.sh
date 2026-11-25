#!/usr/bin/env bash
# 10 yêu cầu song song tranh 1 linh kiện cuối (Mainboard, còn 1): đúng 1 thành công, kho về 0, không âm.
# Cảnh báo: xóa sạch dữ liệu Repair và Inventory (migrate:fresh). Đặt DOCKER=1 nếu hệ thống đang chạy bằng docker compose, SKIP_RESET=1 để không xóa.
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
RUN=$(date +%s)
PARALLEL=10

count_codes() { sort | uniq -c | awk '{printf "%s×%s ", $1, $2}'; }

echo "A. $PARALLEL lệnh giữ linh kiện song song vào Inventory (ref khác nhau)"
reset_data
CODES=$(seq 1 $PARALLEL | xargs -P $PARALLEL -I{} curl -s -o /dev/null -w '%{http_code}\n' -X POST "$INVENTORY/reservations" \
  -H "X-Service-Key: $KEY" -H 'Content-Type: application/json' -d "{\"ref\":\"C$RUN-{}\",\"part_id\":3,\"qty\":1}")
echo "  mã trả về: $(echo "$CODES" | count_codes)"
check "đúng 1 lệnh thành công" "$(echo "$CODES" | grep -c 201)" 1
check "$((PARALLEL - 1)) lệnh bị từ chối hết hàng" "$(echo "$CODES" | grep -c 409)" $((PARALLEL - 1))
check "kho Mainboard = 0" "$(available_of 3)" 0

echo "B. $PARALLEL lệnh cùng ref song song: chỉ trừ kho một lần"
reset_data
CODES=$(seq 1 $PARALLEL | xargs -P $PARALLEL -I{} curl -s -o /dev/null -w '%{http_code}\n' -X POST "$INVENTORY/reservations" \
  -H "X-Service-Key: $KEY" -H 'Content-Type: application/json' -d "{\"ref\":\"SAME$RUN\",\"part_id\":1,\"qty\":1}")
echo "  mã trả về: $(echo "$CODES" | count_codes)"
check "không có lỗi" "$(echo "$CODES" | grep -cvE '^(200|201)$')" 0
check "kho Màn hình 10 → 9" "$(available_of 1)" 9

echo "C. $PARALLEL phiếu song song (khác key) cùng đòi linh kiện cuối qua Repair"
reset_data
CODES=$(seq 1 $PARALLEL | xargs -P $PARALLEL -I{} curl -s -o /dev/null -w '%{http_code}\n' -X POST "$REPAIR/tickets" \
  -H "X-Service-Key: $KEY" -H 'Content-Type: application/json' -H "Idempotency-Key: P$RUN-{}" \
  -d '{"serial":"SN-LAP-001","customer":"A","phone":"0901234567","issue":"x","part_id":3,"qty":1}')
echo "  mã trả về: $(echo "$CODES" | count_codes)"
check "đúng 1 phiếu thành công" "$(echo "$CODES" | grep -c 201)" 1
call GET "$REPAIR/tickets?status=RECEIVED"
check "1 phiếu RECEIVED" "$(python3 -c 'import json,sys;print(len(json.loads(sys.argv[1])["data"]))' "$BODY")" 1
check "kho Mainboard = 0" "$(available_of 3)" 0

echo "D. $PARALLEL yêu cầu song song cùng Idempotency-Key: chỉ một phiếu"
reset_data
seq 1 $PARALLEL | xargs -P $PARALLEL -I{} curl -s -o /dev/null -X POST "$REPAIR/tickets" \
  -H "X-Service-Key: $KEY" -H 'Content-Type: application/json' -H "Idempotency-Key: ONE$RUN" \
  -d '{"serial":"SN-LAP-001","customer":"A","phone":"0901234567","issue":"x","part_id":1,"qty":1}'
call GET "$REPAIR/tickets"
check "1 phiếu" "$(python3 -c 'import json,sys;print(len(json.loads(sys.argv[1])["data"]))' "$BODY")" 1
check "kho Màn hình 10 → 9" "$(available_of 1)" 9

finish
