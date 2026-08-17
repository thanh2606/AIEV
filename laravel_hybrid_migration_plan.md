# Kế hoạch Chuyển đổi sang Mô hình Hybrid (Laravel + Node.js Worker)

Tài liệu này xác định kiến trúc tổng thể, luồng giao tiếp dữ liệu và lộ trình từng bước để chuyển đổi hệ thống AIEV từ Backend Node.js đơn khối sang **Mô hình Hybrid: Laravel Main API Backend kết hợp Node.js Worker Service & Task-Driven Queue Architecture**.

---

## 🏛️ 1. Sơ đồ Kiến trúc Hệ thống Task-Based Hybrid

```
┌───────────────────────────────────────────────────────────────────────────┐
│                           Next.js Web Dashboard                           │
│                          (apps/web - Port 6868)                           │
└─────────────────────────────────────┬─────────────────────────────────────┘
                                      │ 1. REST API Request
                                      ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                        Laravel Main API Backend                           │
│                      (apps/laravel-api - Port 8000)                       │
└─────────────────────────────────────┬─────────────────────────────────────┘
                                      │ 2. Yêu cầu Lập Kế hoạch (Single-turn)
                                      ▼
┌───────────────────────────────────────────────────────────────────────────┐
│              Node.js Worker Service (Claude AI Planner)                    │
│  - Suy luận 1 turn duy nhất ➔ Trả về JSON: JobSchedulePlan (Danh sách Tasks)│
└─────────────────────────────────────┬─────────────────────────────────────┘
                                      │ 3. Trả về JSON: Array<Task>
                                      ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                  Laravel Horizon Queue (Redis Dispatcher)                 │
│  - Phân phối & quản lý Hàng đợi công việc (Retry, Rate limit, Concurrency) │
└───────┬─────────────────────────────┼─────────────────────────────┬───────┘
        │                             │                             │
        ▼                             ▼                             ▼
┌───────────────┐             ┌───────────────┐             ┌───────────────┐
│  PHP Worker 1 │             │  PHP Worker 2 │             │  PHP Worker 3 │
│ Sinh ảnh AI   │             │ Render Scene  │             │ Assemble Video│
│ (Gemini API)  │             │ (HyperFrames) │             │ (Remotion CLI)│
└───────┬───────┘             └───────┬───────┘             └───────┬───────┘
        │                             │                             │
        └─────────────────────────────┼─────────────────────────────┘
                                      │ 4. Push Realtime Progress (Reverb / WebSockets)
                                      ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                       Next.js UI Progress Bar (0-100%)                    │
└───────────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 2. Phân chia Trách nhiệm (Separation of Concerns)

### A. Laravel Main API Backend (`apps/laravel-api`)
1. **Quản lý Dữ liệu**: Thay thế `db.ts` hiện tại bằng Laravel Eloquent ORM & Migrations (`projects`, `chat_sessions`, `render_settings`, `jobs`).
2. **Task Queue Master (Laravel Horizon)**: Đẩy mảng Tasks nhận từ Claude vào Redis Queue, tự động retry khi fail, giới hạn số job render GPU chạy cùng lúc.
3. **Thực thi Công việc Chuyên biệt (PHP Workers)**:
   - Worker 1 (`GenerateImageJob`): Gọi Gemini API tạo ảnh.
   - Worker 2 (`RenderSceneJob`): Gọi CLI `npx hyperframes render...`.
   - Worker 3 (`AssembleVideoJob`): Gọi CLI `npx remotion render...`.
   - Worker 4 (`RunQcJob`): Gọi `ffprobe` tự động kiểm tra chất lượng video.
4. **Realtime Progress Stream**: Dùng **Laravel Reverb** (WebSockets) đẩy tiến độ render từ Redis Pub/Sub về cho Next.js Web UI.

### B. Node.js Worker Service (`apps/node-worker`)
1. **Claude AI Planner Only**: Giữ lại `agent.ts`, nạp `AGENT_INSTRUCTIONS.md` và giao tiếp với Claude Agent SDK (`@anthropic-ai/claude-agent-sdk`).
2. **Single-turn Task Schedule Generator**: Claude chỉ suy luận **đúng 1 lượt (Single-turn)** để xuất ra JSON `JobSchedulePlan`, không trực tiếp chạy lệnh Bash hay đợi render.

---

## 📋 3. Lộ trình Triển khai từng Giai đoạn (Migration Roadmap)

### Giai đoạn 1: Chuẩn hóa Schema & API Contract
- [ ] **Task 1.1**: Chuyển đổi SQLite Schema trong `db.ts` sang Laravel Database Migrations.
- [ ] **Task 1.2**: Định nghĩa OpenAPI / Swagger Specs cho các API giữa Web UI ➔ Laravel và giữa Laravel ➔ Node Worker.

### Giai đoạn 2: Khởi tạo Laravel API App (`apps/laravel-api`)
- [ ] **Task 2.1**: Tạo dự án Laravel 11 (`npx / composer create-project laravel/laravel apps/laravel-api`).
- [ ] **Task 2.2**: Cài đặt Laravel Horizon, Predis, Laravel Sanctum (Auth) và Laravel Reverb (Realtime).
- [ ] **Task 2.3**: Porting các API Routes từ `apps/server/src/routes/` (`textToVideo.ts`, `jobs.ts`, `config.ts`, `connections.ts`) sang Laravel Controllers.
- [ ] **Task 2.4**: Tích hợp module bóc tách bài viết `ArticleExtractorService` bằng PHP Readability.

### Giai đoạn 3: Tách `apps/server` thành Node.js Worker Microservice (`apps/node-worker`)
- [ ] **Task 3.1**: Tóm gọn `apps/server` hiện tại thành `apps/node-worker`, xóa bỏ phần HTTP API dư thừa của Express.
- [ ] **Task 3.2**: Xây dựng 3 Internal REST/gRPC Endpoints:
  - `POST /internal/agent/plan`: Nhận prompt kịch bản ➔ Claude suy luận 1 turn ➔ Trả về JSON `JobSchedulePlan`.
  - `POST /internal/transcribe`: Nhận audio ➔ Chạy Whisper `transcribe.js`.
- [ ] **Task 3.3**: Cắt bỏ quyền chạy Tool Bash trực tiếp của Claude Agent để đảm bảo an toàn & tiết kiệm token.

### 🌟 Giai đoạn 4: Triển khai Kiến trúc Task-Based Queue Driven (PHP Worker Execution)
- [ ] **Task 4.1**: Thiết kế `JobSchedulePlan` Schema (Zod / JSON Schema) chuẩn hóa mảng Tasks.
- [ ] **Task 4.2**: Xây dựng bộ **PHP Worker Jobs** trong `app/Jobs/`:
  - `GenerateImageJob.php`: Sinh ảnh AI theo prompt.
  - `RenderSceneJob.php`: Thực thi CLI HyperFrames.
  - `AssembleVideoJob.php`: Thực thi CLI Remotion.
  - `RunQcJob.php`: Chạy `ffprobe` kiểm tra chất lượng video.
- [ ] **Task 4.3**: Xây dựng `JobDispatcherService` nhận JSON `JobSchedulePlan` từ Claude và đẩy chuỗi Jobs (`Bus::chain()` hoặc `Bus::batch()`) vào Laravel Horizon Queue.
- [ ] **Task 4.4**: Tích hợp Laravel Reverb gửi Event `RenderProgressUpdated` về Web UI theo thời gian thực (0% ➔ 100%).

### Giai đoạn 5: Cập nhật Web UI & Kiểm thử End-to-End
- [ ] **Task 5.1**: Cấu hình `apps/web` (Next.js) trỏ API base URL về Laravel Backend (`http://localhost:8000/api/v1`).
- [ ] **Task 5.2**: Kết nối WebSockets / Reverb client hiển thị tiến độ render mượt mà.
- [ ] **Task 5.3**: End-to-End Test toàn bộ luồng: Dán URL bài viết ➔ Claude xuất Task Schedule JSON ➔ PHP Horizon Queue thực thi ➔ Video Final MP4.

---

## 📊 4. Đánh giá Ưu/Nhược điểm & Chi phí Triển khai

| Tiêu chí | Node.js Đơn khối Hiện tại | Mô hình Hybrid (Laravel + Node Worker) | Mô hình Task-Based Queue (Đề xuất Mới) |
|---|---|---|---|
| **Số turns suy luận AI** | 🔴 15 – 25 turns / phiên | 🔴 15 – 25 turns / phiên | 🟢 **Đúng 1 turn duy nhất (Giảm 90% token)** |
| **Quản lý Hàng đợi (Queue)** | 🟡 SQLite in-memory | 🟢 Laravel Horizon + Redis | 🟢 **Laravel Horizon điều phối 100% Tasks** |
| **Độ tin cậy & Retry** | 🔴 AI kẹt ➔ Chết phiên | 🟡 AI kẹt ➔ Chết phiên | 🟢 **PHP Worker tự Retry nếu render lỗi GPU** |
| **Bảo mật System** | 🔴 AI có quyền Bash | 🔴 AI có quyền Bash | 🟢 **AI không có quyền Bash (Pure Data)** |
| **Thời gian Triển khai** | 🟢 Đã hoàn thành | ⏱️ 1.5 - 2 tuần | ⏱️ **2 - 2.5 tuần** |
