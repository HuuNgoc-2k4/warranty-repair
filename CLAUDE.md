# Dự án

Đồ án môn học: hệ thống tiếp nhận bảo hành và sửa chữa thiết bị, kiến trúc vi dịch vụ. Monorepo gồm 4 app Laravel (PHP 8.4, MySQL), mỗi app là một tiến trình riêng với một schema MySQL riêng:

| App | Cổng | Vai trò | Schema |
|---|---|---|---|
| `gateway` | 8000 | Đăng nhập session, giao diện Blade, chuyển tiếp request sang dịch vụ | `wr_gateway` |
| `warranty-service` | 8001 | Tra cứu hạn bảo hành theo serial | `wr_warranty` |
| `repair-service` | 8002 | Phiếu tiếp nhận, vòng đời trạng thái, hủy, điều phối saga | `wr_repair` |
| `inventory-service` | 8003 | Tồn kho, giữ/trả linh kiện, mô phỏng lỗi | `wr_inventory` |

Chạy cả hệ thống: `./run-all.sh` (local) hoặc `docker compose up --build` (Docker).

# Quy ước kiến trúc vi dịch vụ

- Mỗi dịch vụ chỉ truy cập CSDL của chính nó. Dữ liệu của dịch vụ khác chỉ lấy qua API đã công bố; KHÔNG query chéo schema.
- Dịch vụ con chỉ nhận request mang đúng `X-Service-Key` (middleware `service.key`, giá trị từ `SERVICE_KEY` trong `.env`).
- Mô phỏng lỗi nằm ở Inventory, qua header `X-Simulate: fail|slow|commit-then-timeout`, chỉ có hiệu lực khi `APP_SIMULATE=true`.
- Mọi thao tác ghi liên dịch vụ phải idempotent (`Idempotency-Key` ở Repair, `ref` UNIQUE ở Inventory) để gửi lại không tạo trùng.
- Timeout KHÔNG có nghĩa là thao tác thất bại: tra lại kết quả (vd `GET /reservations/{ref}`) trước khi quyết định gửi lại, hủy hay bù trừ. Gọi liên dịch vụ dùng `Http::timeout()` và không retry thao tác ghi trừ khi nó idempotent.
- Đổi hợp đồng API (đường dẫn, field, mã lỗi) thì cập nhật luôn bộ yêu cầu API trong `postman/` và tài liệu trong `docs/`.
- `artisan serve` mặc định đơn luồng và sẽ treo khi các dịch vụ gọi chéo nhau; luôn chạy với `PHP_CLI_SERVER_WORKERS` (đã có trong `run-all.sh` và Docker).

# Giao diện (gateway)

## Mobile-first bắt buộc
- Mọi giao diện phải chạy đúng trên điện thoại, không chỉ desktop. Viết CSS mobile trước, dùng breakpoint `sm:`/`md:`/`lg:` để mở rộng lên — không làm ngược lại.
- KHÔNG được tràn ngang. Bảng phải bọc `overflow-x-auto`; chuỗi dài (tên, ghi chú, serial) dùng `break-words min-w-0`; ảnh dùng `max-w-full`.
- Modal và drawer phải vừa màn hình hẹp: `w-full` + `max-w-*`, nội dung dài thì `overflow-y-auto`.
- Vùng bấm được (nút, link trong bảng, icon action) tối thiểu ~44px chiều cao để bấm bằng ngón tay.

## Frontend
- Tailwind chạy qua CDN JIT trong layout (không `@vite`) → class mới tự có hiệu lực, KHÔNG cần `npm run build`.
- JS của từng trang viết inline trong `@push('scripts')`; helper dùng chung định nghĩa một lần trong `resources/views/layouts/partials/` và gắn lên `window`. Không dùng axios/jQuery.

# Quy ước code

## Naming
- **English only**: MỌI identifier trong code (method, biến, hằng, route URI, action string, cột DB) phải là tiếng Anh — KHÔNG dùng viết tắt tiếng Việt.
- Tiếng Việt chỉ dùng cho: nhãn UI, flash message, message lỗi trả về, message của Validator, comment inline.

## PHP Style
- **Toán tử phủ định** viết liền `!$var`, KHÔNG có dấu cách `! $var`.
- **Trailing comma**: KHÔNG viết dấu phẩy ở phần tử cuối trong array/argument nhiều dòng.
- KHÔNG tự chạy `pint` (nó format cả file, gây diff nhiễu); tự viết đúng style code xung quanh.
- Luôn dùng dấu ngoặc nhọn cho cấu trúc điều khiển; khai báo return type và type hint cho mọi method; enum case viết TitleCase hoặc UPPER_CASE theo enum sẵn có trong app.

## PHPDoc & Comments
- **PHPDoc alignment**: một dấu cách — `@param Type $var`, KHÔNG `@param  Type  $var`.
- **Mọi method public và private** PHẢI có PHPDoc với `@param`, `@return` (+ `@throws` nếu có); mô tả một câu nêu chức năng.
- **Controller flow**: comment đánh số tiếng Việt (`// 1. Validate dữ liệu`, `// 2. Gọi Service xử lý`, `// 3. Phản hồi`).
- **Business logic phức tạp**: comment inline tiếng Việt giải thích WHY, không giải thích WHAT. Không để comment lỗi thời / trùng lặp.
- **Không magic number**: đặt tên hằng cho mọi literal có ý nghĩa nghiệp vụ (timeout, số lần thử lại, ngưỡng thời gian).

## Layering
Luồng: **Controller → Validator → Service → Repository → Model**.
- Controller mỏng: validate → gọi service → trả response; DI Service + Validator qua constructor; `$request->only([...])`, không `$request->all()`. KHÔNG dùng Form Request.
- Validator trả object `\Illuminate\Validation\Validator` (KHÔNG trả bool); mỗi action một method (`storeValidator()`...); message tiếng Việt.
- Service chứa business rule và orchestrate; dùng `DB::transaction()` (hoặc `beginTransaction/commit/rollBack`) cho thao tác ghi.
- **Mọi query DB nằm ở Repository** — Service/Controller KHÔNG gọi `Model::query()`/`Model::where()` trực tiếp (thao tác trên instance đã load như `$model->update([...])` thì được).
- Lỗi cho người dùng/client (luật nghiệp vụ) → throw `App\Exceptions\BusinessException` (message là text trả về, kèm `errorCode` và HTTP status; đã được render JSON trong `bootstrap/app.php`). Lỗi kỹ thuật → `Log::error()` + message chung. KHÔNG để `$e->getMessage()` thô của `\Exception` lọt ra response.
- Chưa dùng `BaseService`, `BaseRepository`, `RepositoryInterface`: chỉ thêm khi có từ hai cài đặt trở lên hoặc cần thật.

## Enum
- Mọi field status/type dùng PHP enum backed string ở `app/Enums/`. Model KHÔNG cast enum, so sánh và ghi DB luôn dùng `->value` (`ReservationStatus::RESERVED->value`).
- Enum có `label()` khi cần hiển thị.

## Migration
- **KHÔNG foreign key constraint** (`->constrained()`, `->foreign()`, `foreignId()`) — chỉ tạo column + index: `unsignedBigInteger('x_id')->index()`. Quan hệ khai báo ở Model.
- Cột enum lưu `string(...)->index()`, KHÔNG dùng `enum()` của MySQL.
- Khóa idempotent phải có `unique`. Không sửa migration đã chạy; muốn đổi thì tạo migration mới.
- Không xóa dòng của bảng nghiệp vụ (phiếu, lượt giữ linh kiện): đảo ngược bằng cờ trạng thái, không `delete`.

# Test

- **Chỉ kiểm tra bằng code**: Pest feature test, `curl`/script gọi API, Postman collection. KHÔNG mở trình duyệt để test giao diện (không dùng Browser pane, Chrome, screenshot, `resize_window`). Người dùng tự test giao diện rồi feedback, Claude sửa theo feedback đó.
- Test là sản phẩm nộp bài: thư mục `tests/` của từng app NẰM trong git.
- Dùng `RefreshDatabase` để schema đến từ migration thật, KHÔNG `Schema::create()` trong test. Chạy test trên schema riêng (`wr_*_test`), KHÔNG chạy trên schema dev vì `RefreshDatabase` drop toàn bộ bảng.
- Không viết factory cho model nghiệp vụ; dựng dữ liệu bằng helper (`makePart()`, `makeTicket()`...) trong `tests/Pest.php` hoặc `Model::create`.
- Chạy test tuần tự, không song song trên cùng một DB. Chạy test hẹp nhất bao phủ thay đổi (`php artisan test --compact --filter=...`).
- Không claim "đã pass / đã xong" khi chưa có output thật của lệnh kiểm tra trong lượt đó.

# Quy trình làm việc

- **Explore first**: đọc file hiện có và pattern xung quanh trước khi viết code; kiểm tra có sẵn thứ dùng lại được chưa.
- **Plan in-chat**: việc không nhỏ thì nêu kế hoạch ngay trong hội thoại (hoặc plan mode) trước khi sửa source. KHÔNG tạo file `plan.md` / `tasks.md` nháp.
- **Docs**: báo cáo và sơ đồ đặt trong `docs/`; khi đổi nghiệp vụ thì cập nhật tài liệu tương ứng. Chỉ tạo file tài liệu khi người dùng yêu cầu.
- Không đổi dependency (`composer.json`) và không tạo thư mục gốc mới khi chưa được đồng ý.
- Trả lời ngắn gọn, nêu rõ file nào đã tạo/sửa.

## Commit
- Chỉ viết **một dòng tiêu đề**. KHÔNG viết phần mô tả (body) và KHÔNG thêm dòng `Co-Authored-By` bên dưới.
- Chỉ commit khi người dùng yêu cầu.
