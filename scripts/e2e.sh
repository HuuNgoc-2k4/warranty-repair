#!/usr/bin/env bash
# Chạy các tình huống chính qua API thật (cần ./run-all.sh hoặc docker compose đang chạy và APP_SIMULATE=true ở Inventory).
# Cảnh báo: xóa sạch dữ liệu Repair và Inventory (migrate:fresh). Đặt SKIP_RESET=1 để bỏ qua (khi chạy trong Docker).
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
RUN=$(date +%s)
reset_data

echo "1. Tra cứu bảo hành"
call GET "$WARRANTY/warranty/SN-LAP-001"; check "còn hạn" "$(jget data.valid)" true
call GET "$WARRANTY/warranty/SN-TAB-003"; check "hết hạn" "$(jget data.valid)" false
call GET "$WARRANTY/warranty/NOPE"; check "serial lạ 404" "$CODE" 404
call GET "$WARRANTY/warranty/SN-LAP-001" "" "X-Service-Key: sai"; check "sai key 401" "$CODE" 401

echo "2. Tạo phiếu thành công (chạm Warranty + Repair + Inventory)"
new_ticket "$RUN-a" SN-LAP-001 1; check "201" "$CODE" 201
check "RECEIVED" "$(jget data.status)" RECEIVED
check "còn bảo hành" "$(jget data.in_warranty)" true
T1=$(jget data.id)
check "tồn Màn hình 10 → 9" "$(available_of 1)" 9

echo "3. Gửi lại cùng Idempotency-Key"
new_ticket "$RUN-a" SN-LAP-001 1; check "200" "$CODE" 200
check "cùng phiếu" "$(jget data.id)" "$T1"
check "kho vẫn 9" "$(available_of 1)" 9
new_ticket "$RUN-a" SN-LAP-001 2; check "cùng key khác nội dung 409" "$CODE" 409

echo "4. Hết bảo hành vẫn nhận phiếu; serial lạ bị từ chối"
new_ticket "$RUN-b" SN-TAB-003 1; check "201" "$CODE" 201
check "in_warranty=false" "$(jget data.in_warranty)" false
T2=$(jget data.id)
new_ticket "$RUN-c" NOPE 1; check "serial lạ 422" "$CODE" 422

echo "5. Hết hàng → phiếu FAILED"
new_ticket "$RUN-d" SN-LAP-001 4; check "409" "$CODE" 409
call GET "$REPAIR/tickets?status=FAILED"; check "có 1 phiếu FAILED" "$(python3 -c 'import json,sys;print(len(json.loads(sys.argv[1])["data"]))' "$BODY")" 1

echo "6. Hủy phiếu trả linh kiện (Inventory dùng ở luồng thứ hai)"
call POST "$REPAIR/tickets/$T2/cancel" "{}"; check "CANCELLED" "$(jget data.status)" CANCELLED
check "kho được trả về 9" "$(available_of 1)" 9
call POST "$REPAIR/tickets/$T2/cancel" "{}"; check "hủy lại vẫn 200" "$CODE" 200
check "kho không cộng thêm" "$(available_of 1)" 9

echo "7. Vòng đời và điều kiện hủy"
call POST "$REPAIR/tickets/$T1/status" '{"status":"DONE"}'; check "nhảy cóc 422" "$CODE" 422
call POST "$REPAIR/tickets/$T1/status" '{"status":"REPAIRING"}'; check "REPAIRING" "$(jget data.status)" REPAIRING
call POST "$REPAIR/tickets/$T1/cancel" "{}"; check "đang sửa không hủy được 409" "$CODE" 409
call POST "$REPAIR/tickets/$T1/status" '{"status":"DONE"}'; check "DONE" "$(jget data.status)" DONE
call POST "$REPAIR/tickets/$T1/status" '{"status":"RETURNED"}'; check "RETURNED" "$(jget data.status)" RETURNED
call GET "$REPAIR/tickets/$T1"; check "có 5 dòng lịch sử" "$(python3 -c 'import json,sys;print(len(json.loads(sys.argv[1])["data"]["events"]))' "$BODY")" 5

echo "8. Inventory ghi xong nhưng timeout (commit-then-timeout): tra lại thay vì gửi lại"
BEFORE=$(available_of 2)
new_ticket "$RUN-e" SN-LAP-001 2 "X-Simulate: commit-then-timeout"; check "201" "$CODE" 201
check "RECEIVED" "$(jget data.status)" RECEIVED
check "chỉ trừ kho 1 lần" "$(available_of 2)" $((BEFORE - 1))

echo "9. Inventory chậm (slow) vượt timeout nhưng cuối cùng vẫn ghi"
BEFORE=$(available_of 2)
new_ticket "$RUN-f" SN-LAP-001 2 "X-Simulate: slow"; check "201" "$CODE" 201
check "RECEIVED" "$(jget data.status)" RECEIVED
check "chỉ trừ kho 1 lần" "$(available_of 2)" $((BEFORE - 1))

echo "10. Inventory lỗi hẳn (fail): phiếu FAILED, kho không đổi"
BEFORE=$(available_of 2)
new_ticket "$RUN-g" SN-LAP-001 2 "X-Simulate: fail"; check "503" "$CODE" 503
check "mã lỗi" "$(jget error_code)" inventory_unavailable
check "kho không đổi" "$(available_of 2)" "$BEFORE"

echo "11. Đối soát"
call POST "$REPAIR/tickets/reconcile" '{"older_than":0}'; check "200" "$CODE" 200
check "không còn PENDING" "$(jget data.pending)" 0

finish
