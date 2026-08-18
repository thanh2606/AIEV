# Danh sách API Migration: Laravel vs Node Worker

> Phân tích dựa trên `laravel_hybrid_migration_plan.md` và toàn bộ source code `apps/server/src/routes/`

---

## NGUYÊN TẮC PHÂN LOẠI

**→ Laravel**: CRUD thuần, quản lý dữ liệu, file system metadata, queue dispatch, không cần AI/Claude SDK trực tiếp  
**→ Node Worker**: Yêu cầu Claude AI Agent SDK, Whisper STT, VieNeu TTS local engine, hoặc là internal endpoint chuyên biệt

---

## 1. SYSTEM & INFRA APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/health` | Kiểm tra ffmpeg, hyperframes, claudeAuth, node version | **Laravel** |
| GET | `/api/overview` | Dashboard: running jobs, recent projects, health tổng hợp | **Laravel** |
| GET | `/api/metrics` | CPU%, GPU% (nvidia-smi), VRAM – poll cho header UI | **Laravel** |
| GET | `/api/doctor` | Kiểm tra môi trường: ffmpeg, Chrome, whisper, Python… | **Laravel** |
| POST | `/api/doctor/fix` | Tự cài một dependency còn thiếu (auto=true) | **Laravel** |
| GET | `/api/events` | SSE stream: job/joblog/agent/upload events | **Laravel** (Reverb WebSocket thay SSE) |
| GET | `/api/lan-info` | IP LAN của máy chủ + port + tunnel domain | **Laravel** |
| GET | `/api/grade-presets` | Danh sách preset màu (static list) | **Laravel** |
| GET | `/media/*` | Serve file tĩnh: video/ảnh trong repo | **Laravel** |

---

## 2. CONNECTIONS & PROVIDERS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/connections` | Trạng thái kết nối Claude/Gemini/OpenAI/Soniox | **Laravel** |
| PUT | `/api/connections/:provider/key` | Lưu/xóa API key vào .env | **Laravel** |
| POST | `/api/connections/:provider/test` | Test kết nối thật tới provider | **Laravel** |
| GET | `/api/providers` | Claude + Gemini: connected, source, models list | **Laravel** |
| GET | `/api/providers/gemini/image-models` | Model tạo ảnh live từ Google API (cache 1h) | **Laravel** |
| GET | `/api/providers/claude/models` | Model Claude live từ Anthropic API (cache 10') | **Laravel** |

---

## 3. PROJECTS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/projects` | Danh sách projects + token usage | **Laravel** |
| POST | `/api/projects` | Tạo project mới, scaffold thư mục | **Laravel** |
| GET | `/api/projects/:id` | Chi tiết project: meta, files, brief, thumbnail | **Laravel** |
| POST | `/api/projects/:id/clone` | Nhân bản project (copy files, reset status) | **Laravel** |
| DELETE | `/api/projects/:id` | Xóa project (cần ?force=true) | **Laravel** |
| PUT | `/api/projects/:id/tags` | Cập nhật tags | **Laravel** |
| PUT | `/api/projects/:id/name` | Đổi tên hiển thị | **Laravel** |
| PUT | `/api/projects/:id/brief` | Partial update Brief (styleId, notes, tone…) | **Laravel** |
| GET | `/api/projects/:id/junk` | Liệt kê file rác (renders, cache, verify, staging) | **Laravel** |
| POST | `/api/projects/:id/junk/clean` | Xóa file rác của project | **Laravel** |
| PUT | `/api/projects/:id/assets/:file/description` | Cập nhật mô tả asset trong assets.json | **Laravel** |
| DELETE | `/api/projects/:id/assets/:file` | Xóa file asset + entry assets.json | **Laravel** |
| POST | `/api/projects/:id/assets/:file/grade-preview` | Sinh ảnh preview các preset màu (ffmpeg) | **Laravel** |
| POST | `/api/projects/:id/assets/:file/grade-frame` | Render 1 frame theo preset + chỉnh tay | **Laravel** |
| PUT | `/api/projects/:id/assets/:file/grade` | Lưu lựa chọn colorGrade/colorAdjust | **Laravel** |
| POST | `/api/projects/:id/edit` | Khởi động phiên AI edit (Claude Agent) → 202 | **Node Worker** (/internal/agent/edit) |
| POST | `/api/projects/:id/thumbnail` | Sinh thumbnail từ frame video (ffmpeg) | **Laravel** |
| GET | `/api/projects/:id/subtitles` | Xuất phụ đề .srt/.vtt từ transcript | **Laravel** |
| POST | `/api/projects/:id/subtitles` | Ghi file .srt/.vtt vào publish/ | **Laravel** |
| GET | `/api/projects/:id/publish` | Lấy gói metadata đã soạn | **Laravel** |
| POST | `/api/projects/:id/publish` | AI soạn title/desc/hashtag + phụ đề (Claude) | **Node Worker** (/internal/agent/publish) |
| GET | `/api/projects/:id/qc` | Lấy kết quả QC report | **Laravel** |
| POST | `/api/projects/:id/qc` | Chạy QC tự động bằng ffmpeg (đồng bộ) | **Laravel** |
| GET | `/api/projects/:id/clips` | Danh sách clip đã gợi ý | **Laravel** |
| POST | `/api/projects/:id/clips/suggest` | AI gợi ý đoạn cắt short (Claude) | **Node Worker** (/internal/agent/clips-suggest) |
| POST | `/api/projects/:id/clips/create` | Tạo project con từ clip đã gợi ý | **Laravel** |
| POST | `/api/projects/:id/repurpose` | Tái chế tỉ lệ khung → project con | **Laravel** |
| GET | `/api/projects/:id/review` | Lấy danh sách review notes | **Laravel** |
| POST | `/api/projects/:id/review` | Thêm review note (atSec, text) | **Laravel** |
| PATCH | `/api/projects/:id/review/:noteId` | Sửa text/status của note | **Laravel** |
| DELETE | `/api/projects/:id/review/:noteId` | Xóa review note | **Laravel** |
| POST | `/api/projects/:id/review/send` | Gửi open notes cho Claude Agent sửa → 202 | **Node Worker** (/internal/agent/review) |
| POST | `/api/projects/:id/auto-trim/analyze` | Phân tích silence/deadweight (ffmpeg, đồng bộ) | **Laravel** |
| POST | `/api/projects/:id/auto-trim/apply` | Cắt khoảng lặng → đẩy vào queue → 202 | **Laravel** |

---

## 4. JOBS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/jobs` | Danh sách jobs (filter by projectId, limit) | **Laravel** |
| GET | `/api/jobs/:id` | Chi tiết job + log | **Laravel** |
| POST | `/api/jobs` | Tạo job mới (scene-render, assemble-draft/final, image-gen, auto-cut, auto-trim…) | **Laravel** |
| POST | `/api/jobs/:id/cancel` | Hủy job đang queued/running | **Laravel** |

---

## 5. CHAT APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/chat/sessions` | Danh sách chat sessions (filter by projectId) | **Laravel** |
| GET | `/api/chat/:sessionId/messages` | Lịch sử messages của session | **Laravel** |
| POST | `/api/chat` | Gửi message → Claude Agent chạy nền → 202 | **Node Worker** (/internal/agent/chat) |
| PUT | `/api/chat/:sessionId/auto-resume` | Bật/tắt auto-resume khi agent interrupt | **Laravel** |
| POST | `/api/chat/:sessionId/interrupt` | Dừng agent đang chạy | **Node Worker** (/internal/agent/interrupt) |

---

## 6. TEXT TO VIDEO APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/text-to-video` | Danh sách sessions | **Laravel** |
| POST | `/api/text-to-video` | Tạo session mới | **Laravel** |
| GET | `/api/text-to-video/:id` | Chi tiết session | **Laravel** |
| PATCH | `/api/text-to-video/:id` | Sửa name/source/voice/output/script/brief | **Laravel** |
| DELETE | `/api/text-to-video/:id` | Xóa session | **Laravel** |
| POST | `/api/text-to-video/:id/extract` | Bóc bài viết từ URL (Readability) | **Laravel** |
| POST | `/api/text-to-video/:id/script` | AI viết kịch bản đọc (Claude) | **Node Worker** (/internal/agent/script) |
| POST | `/api/text-to-video/:id/build` | TTS + dựng video → đẩy queue → 202 | **Laravel** |

---

## 7. TRANSLATE VIDEO APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/translate-video/fonts` | Danh sách font phụ đề | **Laravel** |
| GET | `/api/translate-video/stt-providers` | Provider bóc lời khả dụng + capabilities | **Laravel** |
| GET | `/api/translate-video` | Danh sách phiên dịch | **Laravel** |
| POST | `/api/translate-video` | Tạo phiên mới | **Laravel** |
| GET | `/api/translate-video/:id` | Chi tiết phiên | **Laravel** |
| PATCH | `/api/translate-video/:id` | Sửa lang/mode/subtitleStyle/cues/dub | **Laravel** |
| DELETE | `/api/translate-video/:id` | Xóa phiên + file | **Laravel** |
| POST | `/api/translate-video/:id/source` | Upload video nguồn + ffprobe | **Laravel** |
| POST | `/api/translate-video/:id/transcribe` | Bóc lời (Whisper/Soniox) → queue → 202 | **Node Worker** (/internal/transcribe) |
| POST | `/api/translate-video/:id/translate` | AI dịch cues (Claude, đồng bộ) | **Node Worker** (/internal/agent/translate) |
| POST | `/api/translate-video/:id/dub-preview` | Nghe thử 1 câu lồng tiếng (bytes WAV) | **Node Worker** (/internal/tts/dub-preview) |
| POST | `/api/translate-video/:id/render` | Ghép phụ đề/lồng tiếng → queue → 202 | **Laravel** |

---

## 8. AUTO CUT APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/auto-cut/sources` | Danh sách video trong imports/ | **Laravel** |
| GET | `/api/auto-cut` | Danh sách phiên cắt | **Laravel** |
| POST | `/api/auto-cut` | Tạo phiên cắt mới (ffprobe đo nguồn) | **Laravel** |
| GET | `/api/auto-cut/:id` | Chi tiết phiên cắt | **Laravel** |
| PATCH | `/api/auto-cut/:id` | Sửa params/output/segments/autoEdit | **Laravel** |
| DELETE | `/api/auto-cut/:id` | Xóa phiên cắt | **Laravel** |
| POST | `/api/auto-cut/:id/plan` | Transcribe + AI chọn đoạn → queue → 202 | **Node Worker** (/internal/agent/autocut-plan) |
| POST | `/api/auto-cut/:id/cut` | Cắt video theo kế hoạch → queue → 202 | **Laravel** |

---

## 9. TTS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/tts/models` | Danh sách model TTS (Google, cache 1h) | **Laravel** |
| GET | `/api/tts/engines` | Engine TTS khả dụng (gemini / vieneu) | **Laravel** |
| GET | `/api/tts/voices` | Danh sách 30 giọng Gemini + giọng VieNeu | **Laravel** |
| GET | `/api/tts/languages` | Danh sách mã ngôn ngữ TTS | **Laravel** |
| POST | `/api/tts/preview` | Đọc thử (bytes WAV) – Gemini hoặc VieNeu | **Node Worker** (/internal/tts/preview) |

---

## 10. VOICES (Cloned) APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/voices` | Danh sách giọng đã nhân bản | **Laravel** |
| POST | `/api/voices` | Upload file mẫu → tạo giọng nhân bản (VieNeu) | **Node Worker** (/internal/tts/clone) |
| PATCH | `/api/voices/:id` | Sửa name/gender/note | **Laravel** |
| DELETE | `/api/voices/:id` | Xóa giọng nhân bản | **Laravel** |
| POST | `/api/voices/:id/preview` | Nghe thử giọng nhân bản (bytes WAV) | **Node Worker** (/internal/tts/clone-preview) |

---

## 11. STYLES & VIDEO STYLES APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/styles` | Danh sách style design | **Laravel** |
| GET | `/api/styles/:id` | Chi tiết style | **Laravel** |
| POST | `/api/styles` | Tạo style mới | **Laravel** |
| PUT | `/api/styles/:id` | Cập nhật style | **Laravel** |
| DELETE | `/api/styles/:id` | Xóa style | **Laravel** |
| GET | `/api/video-styles` | Danh sách video style (giấy gấp, mực tàu…) | **Laravel** |
| GET | `/api/video-styles/:id` | Chi tiết video style | **Laravel** |

---

## 12. ASSETS & MEDIA APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/assets` | Danh sách file theo scope (imports/outputs/project) | **Laravel** |
| POST | `/api/assets` | Upload file (multipart), SSE progress | **Laravel** |
| GET | `/api/upload-session` | – | – |
| POST | `/api/upload-session` | Tạo token QR upload (TTL 60 phút) | **Laravel** |
| DELETE | `/api/upload-session/:token` | Thu hồi token QR | **Laravel** |

---

## 13. SFX, MUSIC, ILLUSTRATIONS, BRAND LOGOS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/sfx` | Danh sách sound effects | **Laravel** |
| POST | `/api/sfx` | Upload SFX mới | **Laravel** |
| DELETE | `/api/sfx/:id` | Xóa SFX | **Laravel** |
| POST | `/api/sfx/search` | Tìm SFX (tên, tag) | **Laravel** |
| GET | `/api/music` | Danh sách nhạc nền | **Laravel** |
| POST | `/api/music` | Upload nhạc | **Laravel** |
| DELETE | `/api/music/:id` | Xóa nhạc | **Laravel** |
| GET | `/api/illustrations` | Danh sách minh họa | **Laravel** |
| GET | `/api/brand-logos` | Danh sách logo brand | **Laravel** |
| POST | `/api/brand-logos/fetch` | Tải logo từ URL/domain | **Laravel** |

---

## 14. IMAGES (AI Generated) APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/images` | Danh sách image projects | **Laravel** |
| POST | `/api/images` | Tạo image project mới | **Laravel** |
| GET | `/api/images/:id` | Chi tiết image project | **Laravel** |
| PATCH | `/api/images/:id` | Sửa image project | **Laravel** |
| DELETE | `/api/images/:id` | Xóa image project | **Laravel** |
| POST | `/api/images/:id/generate` | Tạo ảnh AI (Gemini) → đẩy queue → 202 | **Laravel** |

---

## 15. SKILLS & PROMPTS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/skills` | Danh sách skills | **Laravel** |
| POST | `/api/skills` | Tạo skill mới | **Laravel** |
| GET | `/api/skills/:id` | Chi tiết skill | **Laravel** |
| PUT | `/api/skills/:id` | Cập nhật skill | **Laravel** |
| DELETE | `/api/skills/:id` | Xóa skill | **Laravel** |
| GET | `/api/prompts` | Danh sách saved prompts | **Laravel** |
| POST | `/api/prompts` | Lưu prompt | **Laravel** |
| DELETE | `/api/prompts/:id` | Xóa prompt | **Laravel** |

---

## 16. USAGE & RENDER SETTINGS APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/usage/summary` | Tổng token/cost by project | **Laravel** |
| GET | `/api/usage/timeline` | Token usage theo ngày (days, scope) | **Laravel** |
| GET | `/api/usage/by-model` | Breakdown cost by model | **Laravel** |
| GET | `/api/render-settings` | Đọc cài đặt render + hardware detect | **Laravel** |
| PUT | `/api/render-settings` | Cập nhật workers/concurrency/qcGate… | **Laravel** |

---

## 17. UPDATE, REVEAL, TUNNEL APIs

| Method | Endpoint | Mô tả | Migration |
|--------|----------|-------|-----------|
| GET | `/api/update` | Kiểm tra phiên bản mới | **Laravel** |
| POST | `/api/update` | Cập nhật ứng dụng | **Laravel** |
| GET | `/api/reveal` | Mở file trong Finder/Explorer | **Laravel** |
| GET | `/api/tunnel` | Trạng thái Cloudflare tunnel | **Laravel** |
| POST | `/api/tunnel/start` | Bật Cloudflare Quick Tunnel | **Laravel** |
| DELETE | `/api/tunnel` | Tắt tunnel | **Laravel** |

---

## TÓM TẮT PHÂN LOẠI

| Nhóm | Tổng API | Laravel | Node Worker |
|------|----------|---------|-------------|
| System & Infra | 9 | 9 | 0 |
| Connections & Providers | 6 | 6 | 0 |
| Projects | 35 | 31 | 4 |
| Jobs | 4 | 4 | 0 |
| Chat | 5 | 3 | 2 |
| Text To Video | 8 | 6 | 2 |
| Translate Video | 12 | 8 | 4 |
| Auto Cut | 8 | 6 | 2 |
| TTS | 5 | 4 | 1 |
| Voices | 5 | 3 | 2 |
| Styles & Video Styles | 7 | 7 | 0 |
| Assets & Media | 5 | 5 | 0 |
| SFX/Music/etc | 10 | 10 | 0 |
| Images | 6 | 6 | 0 |
| Skills & Prompts | 8 | 8 | 0 |
| Usage & Render Settings | 5 | 5 | 0 |
| Update/Reveal/Tunnel | 6 | 6 | 0 |
| **TỔNG** | **144** | **127** | **17** |

---

## NODE WORKER – INTERNAL ENDPOINTS CẦN XÂY DỰNG

Theo `laravel_hybrid_migration_plan.md` Task 3.2, Node Worker expose các internal endpoint sau để Laravel gọi:

| Internal Endpoint | Tương đương API cũ | Mô tả |
|-------------------|--------------------|-------|
| `POST /internal/agent/chat` | `/api/chat` (POST) | Claude chat + tool use |
| `POST /internal/agent/interrupt` | `/api/chat/:id/interrupt` | Dừng agent |
| `POST /internal/agent/edit` | `/api/projects/:id/edit` | AI edit project |
| `POST /internal/agent/review` | `/api/projects/:id/review/send` | Sửa theo ghi chú duyệt |
| `POST /internal/agent/publish` | `/api/projects/:id/publish` (POST) | AI soạn metadata đăng bài |
| `POST /internal/agent/clips-suggest` | `/api/projects/:id/clips/suggest` | AI gợi ý clip |
| `POST /internal/agent/script` | `/api/text-to-video/:id/script` | AI viết kịch bản |
| `POST /internal/agent/translate` | `/api/translate-video/:id/translate` | AI dịch cues |
| `POST /internal/agent/autocut-plan` | `/api/auto-cut/:id/plan` | Transcribe + AI chọn đoạn |
| `POST /internal/transcribe` | `/api/translate-video/:id/transcribe` | Whisper/Soniox bóc lời |
| `POST /internal/tts/preview` | `/api/tts/preview` | TTS nghe thử (Gemini/VieNeu) |
| `POST /internal/tts/dub-preview` | `/api/translate-video/:id/dub-preview` | Nghe thử lồng tiếng |
| `POST /internal/tts/clone` | `/api/voices` (POST) | Nhân bản giọng VieNeu |
| `POST /internal/tts/clone-preview` | `/api/voices/:id/preview` | Nghe thử giọng nhân bản |
