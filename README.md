# Hệ thống tiếp nhận bảo hành và sửa chữa thiết bị

Đồ án môn học: hệ thống kiến trúc vi dịch vụ gồm 4 app Laravel (PHP 8.4, MySQL), mỗi app là một tiến trình riêng với schema MySQL riêng, giao tiếp với nhau qua REST/JSON. Báo cáo đầy đủ (kiến trúc, dữ liệu, sơ đồ tuần tự, xử lý lỗi, kết quả kiểm thử) nằm ở [docs/report.md](docs/report.md).

## Kiến trúc

```
Trình duyệt ──► gateway :8000 ──┬──► warranty-service :8001   (wr_warranty)
 (session login, Blade UI)      ├──► repair-service   :8002   (wr_repair) ──► warranty-service
                                │                                          └─► inventory-service
                                └──► inventory-service :8003  (wr_inventory)
```

| App | Cổng | Vai trò | Schema |
|---|---|---|---|
| `gateway` | 8000 | Đăng nhập, giao diện, gọi các dịch vụ (không tính là dịch vụ nghiệp vụ) | `wr_gateway` |
| `warranty-service` | 8001 | Tra cứu hạn bảo hành theo serial | `wr_warranty` |
| `repair-service` | 8002 | Phiếu tiếp nhận, vòng đời trạng thái, hủy, điều phối saga | `wr_repair` |
| `inventory-service` | 8003 | Tồn kho, giữ/trả/dùng linh kiện, mô phỏng lỗi | `wr_inventory` |

Nguyên tắc chính:

- Mỗi dịch vụ chỉ truy cập CSDL của mình, không khóa ngoại và không query chéo schema.
- Dịch vụ con chỉ nhận request có đúng header `X-Service-Key`.
- Ghi liên dịch vụ idempotent: `Idempotency-Key` ở Repair, `ref` UNIQUE ở Inventory.
- Timeout không có nghĩa là thất bại: tra lại trạng thái (`GET /reservations/{ref}`) trước khi gửi lại, hủy hoặc bù trừ.
- Trừ kho nguyên tử bằng `UPDATE ... WHERE available >= qty`, nên đặt hàng đồng thời không làm âm kho.

## Chức năng

- Tạo phiếu tiếp nhận: kiểm tra bảo hành, lưu phiếu, giữ linh kiện (Warranty → Repair → Inventory).
- Vòng đời phiếu: `RECEIVED → REPAIRING → DONE → RETURNED`; hủy phiếu (chỉ khi mới tiếp nhận) trả linh kiện về kho.
- Chống gửi trùng, xử lý timeout/trạng thái chưa rõ, đối soát phiếu `PENDING`.
- Giao diện tạm cho nhân viên: đăng nhập, danh sách, tạo phiếu, chi tiết và thao tác, tồn kho.

## Chạy hệ thống

### Docker (khuyến nghị)

Cần Docker và Docker Compose.

```bash
docker compose up --build
```

Lần đầu mỗi container tự tạo `.env`, cài `vendor`, chạy migrate và seed. Mở http://localhost:8000. MySQL chỉ nằm trong mạng Docker. Dừng và xóa dữ liệu: `docker compose down -v`.

### Máy cá nhân

Cần PHP 8.4, Composer, MySQL 8.

1. Tạo 4 schema và user theo [docker/mysql/init.sql](docker/mysql/init.sql) (user `wr` / mật khẩu `wr_dev`).
2. Với từng app (`gateway`, `warranty-service`, `repair-service`, `inventory-service`):

   ```bash
   cd inventory-service
   composer install
   cp .env.example .env && php artisan key:generate
   php artisan migrate
   ```

3. Seed dữ liệu mẫu cho `gateway`, `warranty-service`, `inventory-service`:

   ```bash
   php artisan db:seed
   ```

4. Bật mô phỏng lỗi ở Inventory: đặt `APP_SIMULATE=true` trong `inventory-service/.env`.
5. Chạy cả 4 tiến trình từ thư mục gốc:

   ```bash
   ./run-all.sh
   ```

### Tài khoản và dữ liệu mẫu

| Thành phần | Dữ liệu |
|---|---|
| Nhân viên (gateway) | `staff1@wr.test` / `password`, `staff2@wr.test` / `password` |
| Thiết bị (warranty) | `SN-LAP-001` còn hạn, `SN-PHN-002` còn hạn, `SN-TAB-003` hết hạn, `SN-LAP-004` hết hạn, `SN-TV-005` còn hạn |
| Linh kiện (inventory) | 1 Màn hình (10), 2 Pin (5), 3 Mainboard (1), 4 Camera (0) |

Hạn bảo hành của thiết bị mẫu tính lùi từ ngày chạy seed, nên trạng thái còn/hết hạn luôn đúng.

## API

Mỗi dịch vụ con yêu cầu `X-Service-Key: wr-dev-key` (giá trị `SERVICE_KEY` trong `.env`). Hợp đồng chi tiết ở [docs/report.md](docs/report.md), bộ request mẫu ở [postman/warranty-repair.postman_collection.json](postman/warranty-repair.postman_collection.json) (import vào Postman rồi chạy bằng Collection Runner; nên reset dữ liệu trước mỗi lần chạy).

| Dịch vụ | Endpoint chính |
|---|---|
| Warranty `:8001` | `GET /devices`, `GET /warranty/{serial}` |
| Inventory `:8003` | `GET /parts`, `POST /reservations`, `GET /reservations/{ref}`, `POST /reservations/{ref}/release`, `POST /reservations/{ref}/consume` |
| Repair `:8002` | `POST /tickets` (header `Idempotency-Key`), `GET /tickets`, `GET /tickets/{id}`, `POST /tickets/{id}/status`, `POST /tickets/{id}/cancel`, `POST /tickets/reconcile` |

Ví dụ tạo phiếu:

```bash
curl -X POST http://localhost:8002/tickets \
  -H 'X-Service-Key: wr-dev-key' -H 'Idempotency-Key: demo-1' -H 'Content-Type: application/json' \
  -d '{"serial":"SN-LAP-001","customer":"Nguyen Van A","phone":"0901234567","issue":"Man hinh soc","part_id":1,"qty":1}'
```

Gửi lại đúng lệnh trên trả lại cùng phiếu (HTTP 200) và không trừ kho lần nữa.

### Mô phỏng lỗi

Khi Inventory chạy với `APP_SIMULATE=true`, thêm header `X-Simulate` vào request gửi tới Repair (hoặc chọn trong form ở giao diện); Repair chuyển tiếp xuống Inventory:

| Giá trị | Hành vi của Inventory | Kết quả mong đợi ở Repair |
|---|---|---|
| `fail` | Trả 503 ngay | Phiếu `FAILED`, kho không đổi |
| `slow` | Chậm 5 giây rồi xử lý bình thường | Vượt timeout 3 giây nhưng tra lại thấy đã giữ: phiếu `RECEIVED`, kho trừ đúng một lần |
| `commit-then-timeout` | Ghi DB xong, chờ 10 giây mới trả lời | Tra lại thấy đã giữ: phiếu `RECEIVED`, kho trừ đúng một lần |

Phiếu `PENDING` (không tra được kết quả) được xử lý bằng lệnh đối soát:

```bash
cd repair-service && php artisan tickets:reconcile --older-than=0
```

## Kiểm thử

Test tự động của từng app (SQLite in-memory, không cần MySQL):

```bash
cd repair-service && php artisan test --compact
```

Kịch bản chạy trên hệ thống thật (cần hệ thống đang chạy và Inventory bật `APP_SIMULATE=true`). Hai script này xóa sạch dữ liệu Repair và Inventory (`migrate:fresh`); thêm `DOCKER=1` nếu hệ thống chạy bằng Docker:

```bash
./scripts/e2e.sh          # 12 nhóm tình huống, gồm mô phỏng lỗi và đăng nhập qua gateway
./scripts/concurrency.sh  # 10 yêu cầu song song tranh linh kiện cuối
```

## Cấu trúc thư mục

```
gateway/  warranty-service/  repair-service/  inventory-service/   # 4 app Laravel
docker/                  # Dockerfile, entrypoint, init.sql của MySQL
docker-compose.yml       # chạy toàn bộ hệ thống
run-all.sh               # chạy 4 app trên máy cá nhân
scripts/                 # kịch bản e2e và đồng thời
postman/                 # bộ request API
docs/                    # báo cáo, sơ đồ
CLAUDE.md                # quy ước code và quy trình làm việc
```

Mỗi dịch vụ theo luồng Controller → Validator → Service → Repository → Model. Chi tiết quy ước xem [CLAUDE.md](CLAUDE.md).
