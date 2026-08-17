# Chiến lược Thực thi & Theo dõi Tiến độ Tối ưu hóa AIEV

Tài liệu này theo dõi trạng thái thực thi thực tế của từng task và sub-task trong quy trình tối ưu hóa token và thời gian xử lý.

---

## 📊 Bảng Dashboard Tiến độ Tổng quan

- **Tổng số Sub-tasks**: 15 sub-tasks
- **Trạng thái hiện tại**: 2/15 (13.3%) - *Đã bật cờ Cache & System Prompt tinh gọn*
- **Cập nhật lần cuối**: `2026-08-17 17:00:00`

### Quy ước Trạng thái (Status Legend)
- 🔴 **PENDING** (Chưa bắt đầu)
- 🟡 **IN_PROGRESS** (Đang tiến hành)
- 🟢 **COMPLETED** (Hoàn thành & Kiểm thử OK)
- ⚪ **SKIPPED** (Bỏ qua)

---

## 🛠️ CHI TIẾT TRẠNG THÁI THỰC THI

### PHA 1: THỰC HIỆN NGAY (Quick Wins - Ưu tiên Hàng đầu)

#### 📌 Task 1: Bộ lọc Truncate Log rác cho Tool Bash
*Mục tiêu: Tiết kiệm ~50.000 tokens/phiên bằng cách lọc bớt log tiến trình nạp webpack/progress bar.*

| Sub-task ID | Nội dung công việc | File tác động | Trạng thái | Ngày hoàn thành | Ghi chú / Test Result |
|---|---|---|---|---|---|
| **Sub-task 1.1** | Viết hàm helper `cleanToolOutput(command, output)` | `apps/server/src/agent.ts` | 🔴 **PENDING** | - | Lọc log CLI rác |
| **Sub-task 1.2** | Tích hợp `cleanToolOutput` vào luồng SDK Query | `apps/server/src/agent.ts` | 🔴 **PENDING** | - | Giữ kết quả ngắn gọn |
| **Sub-task 1.3** | Kiểm thử thực tế với lệnh render | Server & Logs | 🔴 **PENDING** | - | Xác nhận log còn 1-2 dòng |

---

#### 📌 Task 2: Xử lý Timeline & Frame bằng Code thuần (Backend Pre-calculation)
*Mục tiêu: Tiết kiệm ~30.000 tokens, bớt 3-5 turns suy luận nhẩm frame.*

| Sub-task ID | Nội dung công việc | File tác động | Trạng thái | Ngày hoàn thành | Ghi chú / Test Result |
|---|---|---|---|---|---|
| **Sub-task 2.1** | Tạo file module `timelineBuilder.ts` | `apps/server/src/timelineBuilder.ts` | 🔴 **PENDING** | - | Hàm `buildTimelineFromTranscript` |
| **Sub-task 2.2** | Thuật toán gom từ & tự động tính frame | `apps/server/src/timelineBuilder.ts` | 🔴 **PENDING** | - | Tính `startFrame`, `durationInFrames` |
| **Sub-task 2.3** | Nhúng vào `prepareEditSession()` | `apps/server/src/childProject.ts` | 🔴 **PENDING** | - | Tạo sẵn khung `props.resolved.json` |

---

#### 📌 Task 3: Tối ưu & Kiểm soát Prompt Caching cho File Tĩnh
*Mục tiêu: Giảm 90% chi phí input token bằng cách kích hoạt Anthropic Prompt Caching.*

| Sub-task ID | Nội dung công việc | File tác động | Trạng thái | Ngày hoàn thành | Ghi chú / Test Result |
|---|---|---|---|---|---|
| **Sub-task 3.1** | Bật `promptCaching: true` & beta header | `agent.ts`, `aiText.ts` | 🟢 **COMPLETED** | 2026-08-17 | Đã thêm cờ cache |
| **Sub-task 3.2** | Định vị `AGENT_INSTRUCTIONS.md` ở đầu | `agent.ts` | 🟢 **COMPLETED** | 2026-08-17 | Dùng file prompt tinh gọn |
| **Sub-task 3.3** | Kiểm tra log API hit cache | Anthropic API Logs | 🔴 **PENDING** | - | Kiểm chứng tỉ lệ hit cache |

---

### PHA 2: MỞ RỘNG KIẾN TRÚC (Nâng cao)

#### 📌 Task 4: Gộp thao tác hàng loạt (Batch Structured Output)
*Mục tiêu: Giảm số turns từ 15-20 xuống còn 3-4 turns.*

| Sub-task ID | Nội dung công việc | File tác động | Trạng thái | Ngày hoàn thành | Ghi chú / Test Result |
|---|---|---|---|---|---|
| **Sub-task 4.1** | Thiết kế Zod Schema cho `BatchSceneConfig` | `apps/server/src/schema.ts` | 🔴 **PENDING** | - | Schema prompt ảnh + template |
| **Sub-task 4.2** | Cập nhật chỉ thị trong `AGENT_INSTRUCTIONS.md` | `AGENT_INSTRUCTIONS.md` | 🔴 **PENDING** | - | Ép AI trả JSON Batch turn 1 |
| **Sub-task 4.3** | Viết backend batch execution handler | `apps/server/src/agent.ts` | 🔴 **PENDING** | - | Tự gọi job sinh ảnh & ghép props |

---

#### 📌 Task 5: Xây dựng Thư viện Component Template & Prop Injector
*Mục tiêu: Giảm 80% Output tokens bằng cách điền Props vào Template có sẵn.*

| Sub-task ID | Nội dung công việc | File tác động | Trạng thái | Ngày hoàn thành | Ghi chú / Test Result |
|---|---|---|---|---|---|
| **Sub-task 5.1** | Đóng gói 5 Remotion Component Templates | `engines/remotion/src/templates/` | 🔴 **PENDING** | - | Title, SplitMedia, Quote, Stats, Karaoke |
| **Sub-task 5.2** | Viết module `templateInjector.ts` | `apps/server/src/templateInjector.ts` | 🔴 **PENDING** | - | Ghép JSON vào `props.resolved.json` |
| **Sub-task 5.3** | Cập nhật Prompt Agent chỉ sinh Props JSON | `AGENT_INSTRUCTIONS.md` | 🔴 **PENDING** | - | Cấm AI viết HTML thô |
| **Sub-task 5.4** | Kiểm thử render video với 5 templates | Render Engine | 🔴 **PENDING** | - | Đảm bảo video nét & đúng nhịp |
