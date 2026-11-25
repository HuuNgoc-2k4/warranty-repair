# Hệ thống tiếp nhận bảo hành và sửa chữa thiết bị

Đồ án môn học: hệ thống kiến trúc vi dịch vụ gồm 4 app Laravel (PHP 8.4, MySQL), mỗi app là một tiến trình riêng với schema MySQL riêng, giao tiếp với nhau qua REST/JSON.

## Kiến trúc

```
Trình duyệt ──► gateway :8000 ──┬──► warranty-service :8001   (wr_warranty)
 (session login, Blade UI)      ├──► repair-service   :8002   (wr_repair) ──► warranty-service
                                │                                          └─► inventory-service
                                └──► inventory-service :8003  (wr_inventory)
```

| App | Cổng | Vai trò | Schema |
|---|---|---|---|
| `gateway` | 8000 | Đăng nhập, giao diện, chuyển tiếp request (không tính là dịch vụ nghiệp vụ) | `wr_gateway` |
| `warranty-service` | 8001 | Tra cứu hạn bảo hành theo serial | `wr_warranty` |
| `repair-service` | 8002 | Phiếu tiếp nhận, vòng đời trạng thái, hủy, điều phối saga | `wr_repair` |
| `inventory-service` | 8003 | Tồn kho, giữ/trả linh kiện, mô phỏng lỗi | `wr_inventory` |

Nguyên tắc chính:

- Mỗi dịch vụ chỉ truy cập CSDL của mình, không khóa ngoại và không query chéo schema.
- Dịch vụ con chỉ nhận request có đúng header `X-Service-Key`.
- Ghi liên dịch vụ phải idempotent (`ref` UNIQUE ở Inventory, `Idempotency-Key` ở Repair) để gửi lại không tạo trùng.
- Timeout không có nghĩa là thất bại: tra lại trạng thái (`GET /reservations/{ref}`) trước khi gửi lại, hủy hoặc bù trừ.
- Trừ tồn kho nguyên tử bằng `UPDATE ... WHERE available >= qty`, nên đặt hàng đồng thời không làm âm kho.

## Tiến độ

| Thành phần | Trạng thái |
|---|---|
| Khung 4 app, Docker, `run-all.sh` | Xong |
| Inventory service (API, idempotent, mô phỏng lỗi) | Xong |
| Warranty service, Repair service (luồng tạo phiếu) | Chưa làm |
| Gateway UI, hủy phiếu, reconcile, test, Postman, báo cáo | Chưa làm |

## Chạy hệ thống

### Docker (khuyến nghị)

Cần Docker và Docker Compose.

```bash
docker compose up --build
```

Lần đầu container tự tạo `.env`, cài `vendor`, chạy migrate và seed (Inventory). Các dịch vụ chạy ở `http://localhost:8000` – `8003`; MySQL chỉ nằm trong mạng Docker.

### Máy cá nhân

Cần PHP 8.4, Composer, MySQL 8.

1. Tạo 4 schema và user theo [docker/mysql/init.sql](docker/mysql/init.sql) (user `wr` / mật khẩu `wr_dev`).
2. Với từng app:

   ```bash
   cd inventory-service   # lặp lại cho gateway, warranty-service, repair-service
   composer install
   cp .env.example .env && php artisan key:generate
   php artisan migrate
   ```

3. Seed dữ liệu tồn kho:

   ```bash
   cd inventory-service && php artisan db:seed
   ```

4. Chạy cả 4 tiến trình từ thư mục gốc:

   ```bash
   ./run-all.sh
   ```

## Inventory API

Mọi request cần header `X-Service-Key: wr-dev-key` (giá trị `SERVICE_KEY` trong `.env`).

| Method | Đường dẫn | Mô tả |
|---|---|---|
| GET | `/parts` | Danh sách linh kiện và tồn kho |
| POST | `/reservations` | Giữ linh kiện, body `{ref, part_id, qty}`; gửi lại cùng `ref` trả về lượt giữ cũ |
| GET | `/reservations/{ref}` | Tra trạng thái lượt giữ (dùng khi chưa rõ kết quả) |
| POST | `/reservations/{ref}/release` | Trả linh kiện, idempotent |

Mã lỗi: `part_not_found` (404), `out_of_stock` (409), `reservation_not_found` (404), `already_consumed` (409).

Dữ liệu mẫu: Màn hình (10), Pin (5), Mainboard (1), Camera (0).

Ví dụ:

```bash
curl -X POST http://localhost:8003/reservations \
  -H 'X-Service-Key: wr-dev-key' -H 'Accept: application/json' \
  -d 'ref=T1' -d 'part_id=1' -d 'qty=1'
```

### Mô phỏng lỗi

Khi `APP_SIMULATE=true`, thêm header `X-Simulate` vào request:

| Giá trị | Hành vi |
|---|---|
| `fail` | Trả 503 ngay |
| `slow` | Chậm 5 giây rồi xử lý bình thường |
| `commit-then-timeout` | Xử lý và ghi DB xong, nhưng chờ 10 giây mới trả lời (client timeout mà dữ liệu đã đổi) |

## Cấu trúc thư mục

```
gateway/  warranty-service/  repair-service/  inventory-service/   # 4 app Laravel
docker/                  # Dockerfile, entrypoint, init.sql của MySQL
docker-compose.yml       # chạy toàn bộ hệ thống
run-all.sh               # chạy 4 app trên máy cá nhân
CLAUDE.md                # quy ước code và quy trình làm việc
```

Mỗi dịch vụ theo luồng Controller → Validator → Service → Repository → Model. Chi tiết quy ước xem [CLAUDE.md](CLAUDE.md).
