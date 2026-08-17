# Kế hoạch & Lộ trình Tối ưu hóa Token & Tốc độ AIEV Pipeline

> ⚠️ **QUY ĐỊNH BẮT BUỘC TRƯỚC KHI THỰC THI**:
> Tất cả các bước triển khai phải được theo dõi trạng thái (🔴 `PENDING`, 🟡 `IN_PROGRESS`, 🟢 `COMPLETED`) trong file **[execution_strategy.md](file:///home/thanh/Documents/AIEV/execution_strategy.md)** trước và sau khi viết code.

---

## 🎯 Mục tiêu Tối ưu hóa
- **Tiết kiệm Token**: Giảm **80% - 85%** lượng token tiêu thụ mỗi phiên (từ ~200k tokens xuống <40k tokens).
- **Tốc độ xử lý**: Giảm thời gian chờ suy luận từ **2-4 phút xuống 20-40 giây**.
- **Độ ổn định**: Khắc phục dứt điểm nguy cơ rớt phiên do lỗi cú pháp code hoặc vượt trần `maxTurns`.

---

## 🚀 PHA 1: THỰC HIỆN NGAY (Quick Wins - Độ khó: Dễ | Hiệu quả: Rất Cao)

### Task 1: Bộ lọc Truncate Log rác cho Tool Bash
- [ ] **Mô tả**: Lọc sạch các dòng stdout/stderr không cần thiết khi Claude chạy lệnh CLI.
- [ ] **Độ khó**: 🟢 **Rất dễ** | **Hiệu quả**: 🚀 **Rất Cao** (Tiết kiệm ~50.000 tokens/phiên)
- [ ] **File cần tác động**: `apps/server/src/agent.ts`
- [ ] **Chi tiết sub-tasks**:
  - [ ] **Sub-task 1.1**: Viết hàm helper `cleanToolOutput(command, output)` lọc bớt log tiến trình nạp webpack, progress bar của Remotion/HyperFrames.
  - [ ] **Sub-task 1.2**: Tích hợp `cleanToolOutput` vào luồng trả kết quả của SDK Query trong `agent.ts`.
  - [ ] **Sub-task 1.3**: Kiểm thử thực tế với lệnh render để xác nhận log trả về cho Claude chỉ còn 1-2 dòng tóm tắt.

---

### Task 2: Xử lý Timeline & Frame bằng Code thuần (Backend Pre-calculation)
- [ ] **Mô tả**: Viết module backend tự tính toán sơ đồ timeline và frame trước khi khởi động Agent.
- [ ] **Độ khó**: 🟡 **Dễ** | **Hiệu quả**: 🚀 **Rất Cao** (Tiết kiệm ~30.000 tokens, giảm 3-5 turns)
- [ ] **File cần tác động**: `apps/server/src/timelineBuilder.ts` (Tạo mới), `apps/server/src/childProject.ts`
- [ ] **Chi tiết sub-tasks**:
  - [ ] **Sub-task 2.1**: Tạo file `timelineBuilder.ts` chứa hàm `buildTimelineFromTranscript(transcriptJson, fps)`.
  - [ ] **Sub-task 2.2**: Thuật toán gom từ theo dấu câu/đoạn nghĩa, tự động tính `startFrame`, `endFrame`, `durationInFrames`.
  - [ ] **Sub-task 2.3**: Nhúng `buildTimelineFromTranscript` vào `prepareEditSession()` trong `childProject.ts` để khởi tạo sẵn khung `props.resolved.json`.

---

### Task 3: Tối ưu & Kiểm soát Prompt Caching cho File Tĩnh
- [ ] **Mô tả**: Đảm bảo các file dữ liệu tĩnh luôn được lưu cache tối đa trong Agent SDK.
- [ ] **Độ khó**: 🟢 **Rất dễ** | **Hiệu quả**: 🚀 **Rất Cao** (Giảm 90% chi phí input token)
- [ ] **File cần tác động**: `apps/server/src/agent.ts`, `apps/server/src/aiText.ts`
- [ ] **Chi tiết sub-tasks**:
  - [ ] **Sub-task 3.1**: Đặt cờ `promptCaching: true` và header `anthropic-beta: prompt-caching-2024-07-31` ở tất cả vị trí gọi SDK.
  - [ ] **Sub-task 3.2**: Định vị phần `AGENT_INSTRUCTIONS.md` và `transcript.json` ở đầu chuỗi prompt để tận dụng prefix caching.
  - [ ] **Sub-task 3.3**: Soi log API để xác nhận chỉ số `cache_read_input_tokens` tăng lên >80%.

---

## 🛠️ PHA 2: MỞ RỘNG KIẾN TRÚC (Nâng cao - Chia nhỏ Sub-tasks chi tiết)

### Task 4: Gộp thao tác hàng loạt (Batch Structured Output)
- [ ] **Mô tả**: Cấu trúc lại prompt để Claude xử lý đa tác vụ trong 1 turn duy nhất.
- [ ] **Độ khó**: 🟠 **Trung bình** | **Hiệu quả**: 🚀 **Cao** (Giảm số turns từ 15-20 xuống 3-4)
- [ ] **File cần tác động**: `AGENT_INSTRUCTIONS.md`, `apps/server/src/agent.ts`
- [ ] **Chi tiết sub-tasks**:
  - [ ] **Sub-task 4.1**: Thiết kế Zod Schema / JSON Schema chuẩn cho phản hồi Batch (`BatchSceneConfig`: danh sách prompt ảnh + layout lựa chọn cho toàn bộ scenes).
  - [ ] **Sub-task 4.2**: Cập nhật chỉ thị trong `AGENT_INSTRUCTIONS.md` yêu cầu Claude xuất đúng định dạng JSON Batch ngay ở turn suy luận đầu tiên.
  - [ ] **Sub-task 4.3**: Viết handler tiếp nhận JSON Batch trên server để tự động kích hoạt hàng loạt job sinh ảnh và gộp props timeline.

---

### Task 5: Xây dựng Thư viện Component Template & Prop Injector
- [ ] **Mô tả**: Chuyển đổi từ việc AI tự viết HTML/GSAP thô sang mô hình điền thông số (Props) vào Template có sẵn.
- [ ] **Độ khó**: 🟠 **Trung bình** | **Hiệu quả**: 🚀 **Rất Cao** (Giảm 80% Output tokens)
- [ ] **File cần tác động**: `engines/remotion/src/templates/`, `apps/server/src/childProject.ts`, `apps/server/src/templateInjector.ts`
- [ ] **Chi tiết sub-tasks**:
  - [ ] **Sub-task 5.1**: Đóng gói 5 Remotion Component Templates chuẩn (`TitleScene`, `SplitMediaScene`, `QuoteScene`, `BigNumberStatsScene`, `KaraokeSubtitleScene`).
  - [ ] **Sub-task 5.2**: Viết module `templateInjector.ts` chứa hàm `injectTemplateProps(projectDir, scenePropsList)` để tự ghép JSON vào `props.resolved.json`.
  - [ ] **Sub-task 5.3**: Cập nhật Prompt và Skill instructions yêu cầu Claude chỉ trả về JSON chứa tên Template + tham số text/ảnh thay vì tự viết code HTML/GSAP.
  - [ ] **Sub-task 5.4**: Kiểm thử render một video hoàn chỉnh với 5 templates vừa tạo để đảm bảo chất lượng hình ảnh và chuyển cảnh.

---

## 📊 Bảng Thứ tự Triển khai Chi tiết (bao gồm Sub-tasks)

| Thứ tự | Task chính | Sub-tasks | Độ khó | Tiết kiệm Token | Thời gian Xử lý |
|---|---|---|---|---|---|
| **1** | **Task 1: Truncate Bash Log** | 1.1, 1.2, 1.3 | 🟢 Rất dễ | ~50.000 tokens | -10 giây |
| **2** | **Task 2: Pre-calculate Timeline** | 2.1, 2.2, 2.3 | 🟡 Dễ | ~30.000 tokens | -30 giây |
| **3** | **Task 3: Prompt Caching** | 3.1, 3.2, 3.3 | 🟢 Rất dễ | -90% chi phí input | -5 giây |
| **4** | **Task 4: Batch Output** | 4.1, 4.2, 4.3 | 🟠 Trung bình | ~40.000 tokens | -40 giây |
| **5** | **Task 5: Template Injector** | 5.1, 5.2, 5.3, 5.4 | 🟠 Trung bình | ~80.000 tokens | -60 giây |
