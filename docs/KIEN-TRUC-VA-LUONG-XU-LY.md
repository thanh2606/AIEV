# TÀI LIỆU PHÂN TÍCH KIẾN TRÚC & LUỒNG XỬ LÝ HỆ THỐNG AIEV (AI EDIT VIDEO)

> **Phiên bản:** 2.0 (Cập nhật sau đợt chuyển đổi Hybrid Architecture)  
> **Tác giả:** Hệ thống Phân tích Kỹ thuật AIEV / noti.vn  
> **Ngày lập:** 20/08/2026  
> **Mục đích:** Tài liệu kỹ thuật toàn diện làm chuẩn (Single Source of Truth) về cấu trúc thư mục, trách nhiệm từng thành phần, luồng xử lý end-to-end, hợp đồng cổng mạng (Ports), và trạng thái chuyển đổi từ Monolith sang Hybrid.

---

## 📑 MỤC LỤC

1. [TỔNG QUAN HỆ THỐNG & ĐỊNH VỊ CÔNG NGHỆ](#1-tổng-quan-hệ-thống--định-vị-công-nghệ)
2. [BỨC TRANH KIẾN TRÚC TỔNG THỂ (HYBRID ARCHITECTURE)](#2-bức-tranh-kiến-trúc-tổng-thể-hybrid-architecture)
3. [CHI TIẾT CÁC PHÂN HỆ & TRÁCH NHIỆM THÀNH PHẦN](#3-chi-tiết-các-phân-hệ--trách-nhiệm-thành-phần)
   - [3.1. Web Dashboard (`apps/web`)](#31-web-dashboard-appsweb)
   - [3.2. Laravel Main API & Queue Master (`apps/laravel-api`)](#32-laravel-main-api--queue-master-appslaravel-api)
   - [3.3. Node.js Worker Microservice (`apps/node-worker`)](#33-nodejs-worker-microservice-appsnode-worker)
   - [3.4. Scene Engine: HyperFrames](#34-scene-engine-hyperframes)
   - [3.5. Video Assembler: Remotion Engine (`engines/remotion`)](#35-video-assembler-remotion-engine-enginesremotion)
   - [3.6. Legacy Backend (`apps/server`) & Hiện trạng Migration](#36-legacy-backend-appsserver--hiện-trạng-migration)
4. [CẤU TRÚC THƯ MỤC VÀ DỮ LIỆU DỰ ÁN](#4-cấu-trúc-thư-mục-và-dữ-liệu-dự-án)
5. [LUỒNG XỬ LÝ DỮ LIỆU END-TO-END (DATA FLOWS)](#5-luồng-xử-lý-dữ-liệu-end-to-end-data-flows)
   - [5.1. Luồng 1: Tạo Video từ Văn bản / Bài viết (Text-to-Video)](#51-luồng-1-tạo-video-từ-văn-bản--bài-viết-text-to-video)
   - [5.2. Luồng 2: AI Chat Director & Lập Kế hoạch Chỉnh sửa (Single-Turn Planning)](#52-luồng-2-ai-chat-director--lập-kế-hoạch-chỉnh-sửa-single-turn-planning)
   - [5.3. Luồng 3: Tự động cắt gọt video & loại bỏ tạp âm/khoảng lặng (Auto-Cut & Auto-Trim)](#53-luồng-3-tự-động-cắt-gọt-video--loại-bỏ-tạp-âmkhoảng-lặng-auto-cut--auto-trim)
   - [5.4. Luồng 4: Dịch phụ đề & Lồng tiếng Video (Translate & Dubbing)](#54-luồng-4-dịch-phụ-đề--lồng-tiếng-video-translate--dubbing)
   - [5.5. Luồng 5: Render Scene & Lắp ráp Video (Draft → Verify Frame → Final)](#55-luồng-5-render-scene--lắp-ráp-video-draft--verify-frame--final)
6. [HỢP ĐỒNG GIAO TIẾP & BẢNG CỔNG MẠNG (PORTS & CONTRACTS)](#6-hợp-đồng-giao-tiếp--bảng-cổng-mạng-ports--contracts)
7. [HỆ THỐNG SKILLS & PROMPTS TRONG HỆ SINH THÁI CLAUDE](#7-hệ-thống-skills--prompts-trong-hệ-sinh-thái-claude)
8. [CÁC LỖ HỔNG & BẤT ĐỒNG BỘ HIỆN TẠI CẦN XỬ LÝ](#8-các-lỗ-hổng--bất-đồng-bộ-hiện-tại-cần-xử-lý)

---

## 1. TỔNG QUAN HỆ THỐNG & ĐỊNH VỊ CÔNG NGHỆ

**AIEV (AI Edit Video)** là nền tảng tự động hóa sản xuất video ngắn/dài ứng dụng AI tạo sinh, định hướng theo phong cách đồ họa chuyển động chuyên nghiệp (Noti.vn / GĐT phong cách fintech, paper explainer, kinetic typography).

### Triết lý Cốt lõi:
1. **Phân chia vai trò triệt để:**
   - **HyperFrames (HTML + GSAP):** Chuyên trách phần việc tạo scene motion-graphics phức tạp, kinetic text, shader, particle, layout đồ họa tinh xảo.
   - **Remotion (React Video):** Chuyên trách lắp ráp timeline hoàn chỉnh, đồng bộ âm thanh giọng đọc (Voice), hiệu ứng âm thanh (SFX), nhạc nền (BGM với auto-ducking), overlay phụ đề Karaoke, và render video cuối.
   - **Claude AI Agent:** Đóng vai trò **Đạo diễn (Director & Planner)** — suy luận cấu trúc kịch bản, xác định keyframe, chỉ định phong cách dựng.
2. **Nguyên tắc Kiểm soát Chất lượng (QC bắt buộc):**
   - Không bao giờ Final Render khi chưa qua Draft + Verify Frame.
   - Mọi tiến trình nặng (render, encode, transcribe) đều chạy qua Hàng đợi nền (Queue Worker) và cập nhật tiến độ Real-time.

---

## 2. BỨC TRANH KIẾN TRÚC TỔNG THỂ (HYBRID ARCHITECTURE)

Dự án đã và đang chuyển dịch từ kiến trúc **Monolith (Node.js Express + SQLite)** sang **Hybrid Architecture (Laravel 11 API + Node.js Worker + Horizon Queue + Redis + MySQL)**:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Next.js Web Dashboard                             │
│                           (apps/web — Port 6868)                            │
│  - Giao diện giám sát phong cách Shopify Admin (Shopify Polaris-like)        │
│  - Quản lý Projects, Queue, Assets, TTS Voices, Styles, Prompts, Skills     │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ REST API (/api/*) + Static Media (/media/*)
                                       │ (Được Next.js proxy ngầm về Port 8000)
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          Laravel 11 Main API Backend                        │
│                       (apps/laravel-api — Port 8000)                        │
│  - Quản lý dữ liệu tập trung qua Eloquent ORM (MySQL 8.0)                   │
│  - 38 API Controllers phục vụ đầy đủ nghiệp vụ CRUD & File Management        │
│  - Master Queue Dispatcher: Phân phối Job vào Redis                         │
│  - Realtime Broadcasting qua Laravel Reverb (Port 8080)                     │
└──────────────────┬──────────────────────────────────────┬───────────────────┘
                   │ 1. HTTP Internal Post (/internal/*)  │ 2. Dispatches Job
                   ▼                                      ▼
┌──────────────────────────────────────┐   ┌──────────────────────────────────┐
│        Node.js Worker Service        │   │      Laravel Horizon Queue       │
│    (apps/node-worker — Port 6870)    │   │         (Redis Queue)            │
│  - Claude AI Planner (Single-Turn)   │   │  - Supervisor quản lý Workers    │
│  - Whisper Speech-to-Text Engine     │   │  - Concurrency & Retry tự động   │
│  - Gemini & VieNeu TTS Engines       │   └──────────────┬───────────────────┘
│  - AutoTrim Core Engine              │                  │
└──────────────────────────────────────┘                  │ Thực thi ProcessRenderJob
                                                          ▼
                                           ┌──────────────────────────────────┐
                                           │       PHP Worker Process         │
                                           │  - HyperFrames CLI (`npx`)       │
                                           │  - Remotion CLI (`npx`)          │
                                           │  - FFmpeg QC & Thumbnails        │
                                           │  - Broadcast tiến độ Reverb      │
                                           └──────────────────────────────────┘
```

---

## 3. CHI TIẾT CÁC PHÂN HỆ & TRÁCH NHIỆM THÀNH PHẦN

### 3.1. Web Dashboard (`apps/web`)
* **Công nghệ:** Next.js 16 (App Router), React 19, Tailwind CSS, `@fontsource/inter`, Lucide Icons.
* **Cổng chạy:** `http://localhost:6868` (Cổng duy nhất người dùng tương tác).
* **Cấu hình Proxy (`next.config.ts`):**
  * Rewrite toàn bộ request `/api/:path*` sang `http://localhost:8000/api/:path*`.
  * Rewrite toàn bộ request `/media/:path*` sang `http://localhost:8000/media/:path*`.
  * Thiết lập `proxyTimeout: 10 phút`, `proxyClientMaxBodySize: 2GB` để hỗ trợ upload video dung lượng lớn.
* **16 Trang chức năng chính:**
  1. `/` (Dashboard tổng quan, Thống kê Token & Cost, Trạng thái hệ thống).
  2. `/projects` & `/projects/[id]` (Quản lý video project, kịch bản, clips, review, auto-trim, QC).
  3. `/text-to-video` & `/[id]` (Trích xuất bài viết từ URL/Text, sinh kịch bản đọc, tạo voice).
  4. `/translate-video` & `/[id]` (Bóc băng transcript, dịch thuật, lồng tiếng isochrony).
  5. `/auto-cut` & `/[id]` (Cắt video dài thành các short clips).
  6. `/images` & `/[id]` (Tạo ảnh Gemini AI + Remotion poster/thumbnail).
  7. `/styles` & `/[id]` (Style Design: Nhận diện thương hiệu - Màu, Font, Logo).
  8. `/video-styles` & `/[id]` (Phong cách dựng: Chất liệu và chuyển động).
  9. `/queue` (Giám sát hàng đợi Render, log streaming thời gian thực).
  10. `/voices` (Thư viện giọng đọc: 30 giọng Gemini + 14 giọng VieNeu + Nhân bản giọng).
  11. `/assets` (Quản lý footage và tài nguyên người dùng đưa vào).
  12. `/sfx` (Kho hiệu ứng âm thanh 100+ file có gắn thẻ `hay-dung`).
  13. `/prompts` (Thư viện prompt chuẩn cho AI).
  14. `/skills` & `/[name]` (Quản lý và chỉnh sửa trực tiếp 20+ Claude Skills).
  15. `/config` (Cấu hình hệ thống, render settings, doctor check, hardware monitor).
  16. `/connections` (Quản lý API Keys: Anthropic, Gemini, OpenAI, Soniox, Cloudflare Tunnel).
  * Route phụ trợ: `/m/[id]` (Giao diện mobile kết nối qua QR code trên cùng mạng LAN để upload video nhanh từ điện thoại).

---

### 3.2. Laravel Main API & Queue Master (`apps/laravel-api`)
* **Công nghệ:** Laravel 11 (PHP 8.4), Laravel Horizon, Laravel Reverb, Predis.
* **Cổng chạy:** `http://localhost:8000` (Nội bộ & Proxy từ Next.js).
* **Cơ sở dữ liệu (MySQL 8.0):**
  * `aiev_jobs`: Quản lý tiến trình render, trạng thái (`queued`, `running`, `done`, `failed`), % progress, log.
  * `chat_sessions` & `chat_messages`: Lưu trữ lịch sử hội thoại với AI Director.
  * `token_usage`: Thống kê token vào/ra và chi phí ước tính theo từng model AI.
  * `tts_voices`: Cache danh mục giọng đọc preset và giọng nhân bản.
* **Hệ thống Controllers (38 Controllers tại `app/Http/Controllers/Api/V1`):**
  * *Dự án:* `ProjectController`, `ProjectClipsController`, `ProjectPublishController`, `ProjectQcController`, `ProjectReviewController`, `ProjectAutoTrimController`.
  * *Nghiệp vụ AI:* `TextToVideoController`, `TranslateVideoController`, `AutoCutController`, `ChatController`, `IllustrationController`.
  * *Tài nguyên & Cấu hình:* `AssetController`, `VoiceController`, `TtsController`, `StyleController`, `VideoStyleController`, `SfxController`, `MusicController`, `BrandLogoController`, `PromptController`, `SkillController`, `RenderSettingsController`, `ConnectionController`.
  * *Hệ thống & Giám sát:* `HealthController`, `OverviewController`, `MetricsController`, `DoctorController`, `EventController`, `SystemController`, `UpdateController`.
* **Hàng đợi & Worker Jobs (`app/Jobs/`):**
  * `ProcessRenderJob.php`: Worker thực thi CLI HyperFrames (`npx hyperframes render`), Remotion (`npx remotion render`), AutoCut, Text-to-video build.
  * `ProcessChatMessage.php`: Tiếp nhận tin nhắn từ người dùng, gọi `POST /internal/agent/plan` sang Node Worker để lập lịch Task, sau đó nạp danh sách Task vào hàng đợi.
* **Broadcasting Realtime (`App\Events\RenderProgressUpdated`):**
  * Đẩy event `render.progress` qua WebSocket Reverb trên channel `render.{projectId}` và `jobs`.

---

### 3.3. Node.js Worker Microservice (`apps/node-worker`)
* **Công nghệ:** Node.js 22 (ESM, TypeScript), Express 5, `@anthropic-ai/claude-agent-sdk`.
* **Cổng chạy:** `http://localhost:6870` (Chỉ chấp nhận kết nối nội bộ từ Laravel Backend).
* **Danh sách Modules Chuyên trách (`src/*.ts`):**
  * `planner.ts`: **Claude AI Planner (Single-Turn)** — Thay thế cơ chế Agent multi-turn cũ (15–25 turns). Nhận lệnh, chỉ dùng tool đọc file (`Read`, `Glob`, `Grep`), suy luận đúng **1 lượt** và trả về cấu trúc JSON `JobSchedulePlan` gồm danh sách các Task cần thực hiện. Tiết kiệm ~90% chi phí Token.
  * `scripting.ts`: Gọi Claude AI tạo kịch bản video từ nội dung bài viết.
  * `agent.ts`: Các tác vụ AI Agent đặc thù: Dịch thuật (`translateAgent`), Soạn metadata đăng bài đa nền tảng (`publishAgent`), Gợi ý đoạn cắt (`clipsSuggestAgent`), Lập kế hoạch cắt gọt (`autoCutPlanAgent`).
  * `transcribe.ts`: Engine nhận diện giọng nói (STT) tích hợp cả **Faster-Whisper** chạy cục bộ và **Soniox API** cho độ chính xác cao đối với tiếng Việt.
  * `tts.ts`: Engine chuyển văn bản thành giọng nói với **Gemini TTS** (`gemini-2.5-flash-preview-tts`, `gemini-3.1-flash-tts-preview`) với khả năng tính toán F0, điều chỉnh tốc độ, và chia chunk tự động.
  * `ttsLocal.ts` & `voiceStore.ts`: Engine **VieNeu-TTS** chạy offline 100% trên máy, hỗ trợ 14 giọng theo vùng miền và tính năng **Nhân bản giọng nói (Voice Cloning)** từ audio mẫu.
  * `autoTrim.ts`: Thuật toán bóc tách khoảng lặng (silence detection) và loại bỏ filler words (ừm, à, lặp từ).
  * `childProject.ts`, `meta.ts`, `textToVideoMeta.ts`, `imageMeta.ts`: Quản lý đọc ghi metadata và scaffold thư mục dự án con.
* **Hệ thống API Nội bộ (`/internal/*`):**
  * Nhóm Agent: `POST /internal/agent/plan`, `/internal/agent/script`, `/internal/agent/translate`, `/internal/agent/publish`, `/internal/agent/clips-suggest`, `/internal/agent/autocut-plan`.
  * Nhóm TTS: `POST /internal/tts/preview`, `/internal/tts/clone`, `GET /internal/tts/voices`, `GET /internal/tts/engines`.
  * Nhóm STT & Pipeline: `POST /internal/transcribe`, `POST /internal/text-to-video/build`.

---

### 3.4. Scene Engine: HyperFrames
* **Công nghệ:** HyperFrames v0.7.81, HTML5, CSS3, GSAP Animation Engine, Puppeteer / Headless Chromium.
* **Vai trò:** Dựng từng Scene Motion Graphics độc lập.
* **Mỗi Scene là một Web Composition:**
  * Toàn quyền sử dụng sức mạnh của CSS, Canvas, SVG, Shader, GSAP Timeline.
  * Chạy `npx hyperframes render --quality draft` (CRF 28, tốc độ cao để kiểm tra lỗi) và `standard` (bản nét).
  * Tự động giải quyết triệt để lỗi mất dấu tiếng Việt khi sử dụng text gradient hoặc canvas.

---

### 3.5. Video Assembler: Remotion Engine (`engines/remotion`)
* **Công nghệ:** Remotion v4.0.500, React 19, Zod Schema, `@remotion/cli`.
* **Vai trò:** Lắp ráp các mảnh video, footage, âm thanh, phụ đề thành tệp MP4 cuối cùng.
* **Cấu trúc Compositions (`src/`):**
  * `Assemble.tsx`: Composition chính lắp ghép toàn bộ timeline dựa trên file `props.resolved.json` (bản resolved của `meta.json`).
  * `Thumbnail.tsx` & `Poster.tsx`: Tạo thumbnail và poster độ phân giải cao cho các nền tảng mạng xã hội.
  * Các Tracks thành phần:
    * `<SceneClip>`: Hiển thị MP4 scene render từ HyperFrames hoặc Footage gốc đã cắt (hỗ trợ hiệu ứng Camera Move: Zoom/Pan drift).
    * `<Transition>`: Xử lý hiệu ứng chuyển cảnh mượt mà giữa các Scene với cơ chế `transitionOverlap`.
    * `<CaptionTrack>`: Hiển thị phụ đề Karaoke đồng bộ chính xác theo từng từ (`captionCueSchema`).
    * `<SubtitleTrack>`: Hiển thị phụ đề dịch dạng khối chữ (`subtitleCueSchema`).
    * `<HighlightTrack>`: Hiển thị các thẻ ghi chú / Key Visual (Main Key trên đỉnh, Related Keys dưới đáy).
    * `<MusicTrack>`: Nhạc nền có tính năng **Auto-Ducking** (tự động hạ âm lượng 70–80% khi có giọng nói).
    * `<SfxTrack>`: Hiệu ứng âm thanh đặt đúng mốc frame với độ trễ được bù trừ (`mediaStart`).

---

### 3.6. Legacy Backend (`apps/server`) & Hiện trạng Migration
* **Công nghệ cũ:** Node.js Express 5, Better-SQLite3, Agent SDK (multi-turn).
* **Trạng thái:** Toàn bộ mã nguồn vẫn còn lưu trong `apps/server/` với 34 route files để dự phòng. 
* **Tình trạng:** 
  * Giao diện Web đã chuyển 100% sang trỏ vào Laravel Backend (Port 8000).
  * 38/38 nhóm endpoint đã được port sang Laravel API Controller tương ứng (theo `api_migration_list.md`).
  * Lệnh khởi động `npm run start` và `npm run dev:all` ở root `package.json` đã cấu hình chạy Laravel + Node Worker.

---

## 4. CẤU TRÚC THƯ MỤC VÀ DỮ LIỆU DỰ ÁN

```
Edit-Video-AI/
├── apps/
│   ├── web/                     # Next.js Dashboard (Port 6868)
│   ├── laravel-api/             # Laravel Main API + Horizon + Reverb (Port 8000, 8080)
│   ├── node-worker/             # Node.js Worker Service (Port 6870)
│   └── server/                  # (Legacy Backend - Giữ làm fallback)
├── engines/
│   └── remotion/                # Remotion Assembly Engine (Composition Assemble/Thumbnail)
├── video-projects/              # Không gian làm việc của các Video Projects
│   └── <project-slug>/
│       ├── meta.json            # Nguồn sự thật duy nhất của Project
│       ├── props.resolved.json  # Props đã map đường dẫn staging cho Remotion
│       ├── index.html           # Composition gốc HyperFrames
│       ├── compositions/        # Các scene con HyperFrames
│       ├── assets/              # voice.wav, transcript.json, footage, sfx, images
│       └── renders/             # File render tạm thời (draft.mp4, final.mp4, qc-*.jpg)
├── text-to-video/               # Dữ liệu phiên Text to Video (<id>/meta.json, voice, transcript)
├── translate-video/             # Dữ liệu phiên Dịch video (<id>/meta.json, source, dub)
├── auto-cut/                    # Dữ liệu phiên Auto-cut (<id>/meta.json, segments)
├── image-projects/              # Dữ liệu phiên tạo ảnh AI (<id>/meta.json, prompt, output)
├── assets/                      # Thư viện tài nguyên dùng chung
│   ├── brand/                   # Logo dương bản, âm bản, favicon
│   ├── styles/                  # Style Design (styles.json + font TTF/WOFF2)
│   ├── video-styles/            # Phong cách dựng (video-styles.json)
│   ├── sound-effects/           # SFX library (100+ files + library.json)
│   ├── music/                   # BGM library (tag theo mood + library.json)
│   └── brand-logos/             # 116+ Logo SVG các thương hiệu
├── outputs/                     # Thư mục chứa video MP4 xuất bản cuối cùng (<project>-final.mp4)
├── imports/                     # Thư mục chứa file footage thô người dùng tải lên
├── .runtime/                    # Toàn bộ môi trường & cache phụ thuộc (KHÔNG cài ra ngoài ổ hệ thống)
│   ├── venv/                    # Python Virtualenv (chứa vieneu, faster-whisper)
│   ├── models/                  # Whisper models, TTS weights
│   ├── bin/                     # FFmpeg, FFprobe binaries
│   ├── mysql-data/              # Dữ liệu MySQL container
│   └── redis-data/              # Dữ liệu Redis container
├── .claude/
│   └── skills/                  # 20 Claude Skills chuyên sâu
└── start/                       # Bộ kịch bản khởi động hệ thống cho Windows / macOS / Linux
```

---

## 5. LUỒNG XỬ LÝ DỮ LIỆU END-TO-END (DATA FLOWS)

### 5.1. Luồng 1: Tạo Video từ Văn bản / Bài viết (Text-to-Video)

```
[1. Người dùng] ──(Dán URL/Văn bản)──▶ [2. Web UI]
                                             │
                                             ▼ (POST /api/text-to-video)
                                    [3. Laravel API]
                                             │
                                             ├─(Bóc tách nội dung HTML)─▶ Readability
                                             │
                                             ├─(Gửi yêu cầu viết kịch bản)─▶ [4. Node Worker]
                                             │                                     │
                                             │                                     ▼ (Claude 3.5 Sonnet)
                                             │                              Sinh kịch bản tối ưu
                                             │                                     │
                                             │◀────(Trả kịch bản JSON)─────────────┘
                                             │
                                             ▼ (POST /api/text-to-video/{id}/build)
                                    [5. Laravel Horizon Queue]
                                             │
                                             ▼ (Thực thi ProcessRenderJob)
                                    [6. Node Worker Build Pipeline]
                                             │
                                             ├─▶ TTS Engine: Tạo `voice.wav` (Gemini Flash / VieNeu)
                                             ├─▶ Whisper Engine: Bóc mốc thời gian từng từ ra `transcript.json`
                                             └─▶ Scaffold `video-projects/<slug>`:
                                                   - Copy `voice.wav` + `transcript.json` vào `assets/`
                                                   - Tạo `meta.json` liên kết `textToVideoId`
                                             │
                                             ▼
                                    [7. Chuyển giao sang luồng Video Project để Render]
```

---

### 5.2. Luồng 2: AI Chat Director & Lập Kế hoạch Chỉnh sửa (Single-Turn Planning)

Khác biệt cốt lõi giữa hệ thống mới và cũ:

```
=== KIẾN TRÚC MỚI (Single-Turn Task Plan) ===
Người dùng gửi tin nhắn: "Hãy đổi background sang màu tím và thêm SFX tiếng ting ở giây thứ 5"
   │
   ▼
Laravel ChatController lưu Message vào DB ──▶ Dispatch Job ProcessChatMessage
   │
   ▼
Gửi HTTP Request đến Node Worker (`POST /internal/agent/plan`)
   │
   ▼
Claude Agent SDK (CHỈ CẤP QUYỀN ĐỌC: Read, Glob, Grep — KHÔNG BASH)
Suy luận đúng 1 lượt (Single-Turn) ──▶ Xuất JSON chuẩn hóa:
   {
     "text": "Tôi đã cập nhật màu nền và lên lịch render lại scene...",
     "tasks": [
       { "id": "t1", "type": "render-scene-draft", "sceneId": "scene-1", "priority": 1 },
       { "id": "t2", "type": "assemble-draft", "dependsOn": ["t1"], "priority": 2 }
     ]
   }
   │
   ▼
Laravel JobDispatcherService nhận mảng Tasks ──▶ Dispatch chuỗi Bus::chain() vào Horizon Queue
   │
   ▼
PHP Workers trong Horizon tự động chạy từng Task một cách độc lập và ổn định!
```

---

### 5.3. Luồng 3: Tự động cắt gọt video & loại bỏ tạp âm/khoảng lặng (Auto-Cut & Auto-Trim)

1. **Phân tích âm thanh (Auto-Trim Analyze):**
   - Web UI gửi yêu cầu `POST /api/projects/{id}/auto-trim`.
   - Node Worker chạy `autoTrim.ts`: Gọi `ffmpeg -af silencedetect=noise=-38dB:d=0.35` để đo chính xác toàn bộ đoạn ngắt quãng không có tiếng nói.
   - Quét qua `transcript.json` để tìm các từ đệm, từ lặp, khoảng dừng giữa các câu.
   - Trả về danh sách `keepRanges` (các khoảng thời gian cần giữ lại).
2. **Thực thi cắt ghép (Auto-Trim Apply):**
   - Laravel đưa job vào Horizon Queue.
   - Worker áp dụng bộ lọc fade audio **30ms** tại mọi mép nối cắt để loại bỏ hoàn toàn tiếng "click/pop" khi ghép các đoạn âm thanh.
   - Tạo file footage đã gọt sạch và cập nhật lại mốc thời gian trong `transcript.json`.

---

### 5.4. Luồng 4: Dịch phụ đề & Lồng tiếng Video (Translate & Dubbing)

1. **Bóc băng nguồn:** Gọi Whisper STT trích xuất mốc thời gian và chữ gốc.
2. **Dịch thuật:** Claude AI dịch sang ngôn ngữ đích theo từng cụm câu tự nhiên.
3. **Xử lý Đẳng thời (Isochrony Problem) trong Lồng tiếng:**
   - Câu dịch tiếng Việt thường dài hơn tiếng Anh từ 15–25%.
   - Module `dub.ts` tự động tính toán thời lượng tối đa cho phép.
   - Áp dụng đòn bẩy điều tốc thông minh: Co giãn tốc độ đọc (0.9x – 1.25x) bằng thuật toán WSOLA / Rubberband để giọng đọc khít vừa vặn với khung hình gốc mà không bị méo tiếng.

---

### 5.5. Luồng 5: Render Scene & Lắp ráp Video (Draft → Verify Frame → Final)

```
[1. Render Scene Draft] 
   npx hyperframes render --quality draft --output renders/scene1-draft.mp4 (CRF 28)
         │
         ▼
[2. Verify Frame Tự động] 
   ffmpeg trích xuất 3 frame tiêu biểu: 10%, 50%, 90% thời lượng scene
   Chạy QC kiểm tra: Tràn viền (Safe Area), Lỗi font dấu tiếng Việt, Độ tương phản
         │
         ▼ (Nếu đạt chuẩn)
[3. Stage Media vào Remotion]
   Tạo hardlink từ `video-projects/<id>/assets/*` sang `engines/remotion/public/staging/<id>/*`
   Ghi file `props.resolved.json`
         │
         ▼
[4. Lắp ráp Timeline Toàn bài (Assemble Draft)]
   npx remotion render Main --props="<id>/props.resolved.json" --output outputs/<id>-draft.mp4
         │
         ▼
[5. Duyệt & Ghi chú (Review) từ Dashboard]
   Người dùng xem bản Draft trên Web UI, gắn cờ ghi chú tại các mốc giây (atSec)
         │
         ▼ (Bấm Duyệt Final)
[6. Render Final Hoàn thiện]
   npx hyperframes render --quality standard (CRF 18)
   npx remotion render Main (ProRes / H.264 High Quality)
   Đóng gói phụ đề .SRT, .VTT và Metadata đăng bài vào thư mục `publish/`
```

---

## 6. HỢP ĐỒNG GIAO TIẾP & BẢNG CỔNG MẠNG (PORTS & CONTRACTS)

| Dịch vụ / Phân hệ | Cổng | Môi trường | Giao thức | Ghi chú |
|---|---|---|---|---|
| **Web Dashboard (Next.js)** | **6868** | Host / Container | HTTP | Cổng duy nhất người dùng mở trên trình duyệt (`http://localhost:6868`) |
| **Laravel Main API** | **8000** | Host / Container | HTTP / REST | Xử lý toàn bộ logic nghiệp vụ, xác thực qua token `x-aiev-token` |
| **Node.js Worker Service** | **6870** | Host / Container | HTTP Internal | Chỉ nhận request `/internal/*` từ Laravel Backend |
| **Laravel Reverb** | **8080** | Host / Container | WebSocket | Push tiến độ render (0-100%) và sự kiện realtime về Web UI |
| **MySQL Database** | **3306** | Container (`mysql:8.0`) | TCP | Lưu trữ quan hệ: Jobs, Sessions, Messages, Token Usage |
| **Redis Cache & Queue** | **6379** | Container (`redis:alpine`) | TCP | Quản lý hàng đợi Horizon và kênh Pub/Sub Reverb |
| **HyperFrames Studio Preview** | **3002** | Host (tùy chọn) | HTTP | Xem trước và debug các Scene Motion Graphics khi cần |
| **Remotion Studio** | **3000** | Host (tùy chọn) | HTTP | Debug trực quan composition lắp ráp Remotion |

---

## 7. HỆ THỐNG SKILLS & PROMPTS TRONG HỆ SINH THÁI CLAUDE

Hệ thống tích hợp **20 kỹ năng chuyên biệt (Claude Skills)** đặt tại `.claude/skills/`, được tự động nạp khi Claude tương tác:

1. `video-pipeline`: Quy trình chuẩn hóa sản xuất video end-to-end (Draft → Verify → Final).
2. `hyperframes`: Hướng dẫn lập trình Scene HTML/CSS/GSAP, xử lý kinetic typography và shader.
3. `hyperframes-cli`: Lệnh CLI HyperFrames (lint, preview, render, transcribe, tts).
4. `hyperframes-registry`: Cài đặt các Block và Component đồ họa mẫu vào Scene.
5. `remotion-assemble`: Kỹ thuật lắp ráp composition Remotion, đồng bộ audio ducking và transition overlap.
6. `noti-tiktok-vn`: Chuẩn video ngắn TikTok (9:16) phong cách Noti.vn: talking-head + kinetic + karaoke + beat punch-in.
7. `noti-tiktok-full-text`: Định dạng video TikTok giải phẫu bài báo/Paper AI toàn văn không lộ mặt.
8. `noti-youtube-edit`: Định dạng video YouTube dài (16:9), hỗ trợ cả chế độ PiP (Picture-in-Picture) và Full-text.
9. `short-form-video`: Bộ quy tắc sản xuất video ngắn chuẩn 1080x1920 với 10 tiêu chí kiểm duyệt chất lượng.
10. `make-a-video`: Trợ lý phỏng vấn và tạo video tự động từ ý tưởng thô cho người mới bắt đầu.
11. `auto-cut`: Kỹ thuật lọc bỏ khoảng lặng và từ đệm, chia đoạn kịch bản thông minh.
12. `offline-voice`: Quản lý engine VieNeu-TTS chạy offline trên máy và kỹ thuật nhân bản giọng nói.
13. `color-grading`: Chỉnh màu footage (De-log, Tonemap HDR-HLG, LUT màu chuẩn).
14. `key-layout`: Bố cục hiển thị Main Key (trên đỉnh) và Related Keys (dưới đáy) đồng bộ lời nói.
15. `ai-illustrations`: Tạo prompt sinh ảnh minh họa AI bằng Gemini và ghép vào video.
16. `background-music`: Chọn nhạc nền theo cảm xúc (Mood) và thiết lập cấu hình auto-ducking.
17. `gsap`: Sổ tay tham khảo kỹ thuật diễn hoạt GSAP (Tween, Timeline, Stagger, Custom Easing).
18. `website-to-hyperframes`: Chụp ảnh/Quét website để biến thành video giới thiệu sản phẩm.
19. `webui-design`: Quy tắc thiết kế giao diện Web Dashboard (thang chữ 3 bậc, token màu light/dark, primitive components).
20. `skill-authoring`: Quy chuẩn tạo mới và tích lũy bài học kinh nghiệm vào Skills.

---

## 8. CÁC LỖ HỔNG & BẤT ĐỒNG BỘ HIỆN TẠI CẦN XỬ LÝ

Qua quá trình phân tích thực tế mã nguồn, hệ thống đang trong giai đoạn giao thời và tồn tại một số điểm không đồng nhất cần được chuẩn hóa:

1. **Bất đồng bộ trong Script Khởi động (`start/start.sh` và `start/start.ps1`):**
   * *Hiện trạng:* File `start.sh` và `start.ps1` vẫn build và chạy `apps/server` (cổng 6869 cũ), trong khi `package.json` gốc (`npm run start`) và `apps/web/next.config.ts` đã chuyển hướng sang chạy Laravel API (`port 8000`) và Node Worker (`port 6870`).
   * *Giải pháp:* Cần cập nhật `start.sh` và `start.ps1` để khởi động Docker Compose (`docker compose up -d`) hoặc khởi động đồng thời `laravel-api` + `node-worker` + `web`.
2. **Comment và Thông báo lỗi trong `apps/web/src/lib/api.ts`:**
   * Nhiều đoạn comment và chuỗi thông báo lỗi hiển thị `port 6869` thay vì `port 8000`. Cần rà soát và chuẩn hóa toàn bộ chuỗi hiển thị.
3. **Kênh Server-Sent Events (SSE):**
   * Web UI đang lắng nghe SSE tại `/api/events`. Trên Laravel, `EventController::stream` mới chỉ là stub ping giữ kết nối. Cần hoàn thiện chuyển hẳn sang lắng nghe WebSocket thông qua **Laravel Reverb Client (Echo)** hoặc hoàn thiện cơ chế stream Redis Pub/Sub trong `EventController`.
4. **Quyền hạn của Worker:**
   * Cần đảm bảo PHP Worker chạy CLI `npx hyperframes` và `npx remotion` có đầy đủ biến môi trường `PATH` trỏ tới Node 22, FFmpeg, và Chrome binary.

---
*Tài liệu được biên soạn tự động và chuẩn hóa dựa trên cấu trúc mã nguồn thực tế của dự án AIEV.*
