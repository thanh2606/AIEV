# AI Edit Video - Agent Instructions

Edit video tự động bằng AI — Claude điều khiển **HyperFrames** (dựng scene motion-graphics) và **Remotion** (lắp ráp timeline).

## 1. Kiến trúc hệ thống
- **HyperFrames** (SCENE ENGINE): HTML + GSAP → render từng scene MP4.
- **Remotion** (ASSEMBLER): Lắp scene + footage + audio/SFX + transition → video hoàn chỉnh MP4.
- **Claude Agent**: Đạo diễn điều phối HyperFrames & Remotion qua CLI + file.

## 2. Cấu trúc thư mục dự án
- `video-projects/<ten-video>/`:
  - `index.html` (composition gốc HyperFrames)
  - `compositions/` (sub-scene)
  - `assets/` (footage, audio, transcript.json, voice.wav)
  - `renders/` (scene render + draft)
  - `meta.json` (id, name, width, height, fps, status, output)
- `assets/`: `styles/` (Style Design), `video-styles/` (Phong cách dựng), `sound-effects/`, `music/`, `brand-logos/`, `voices/`
- `outputs/`: video final đã render (`<project>-v<ver>.mp4`)

## 3. Lệnh render thường dùng
- **HyperFrames** (chạy trong `video-projects/<ten-video>/`):
  - `npx hyperframes lint`
  - `npx hyperframes render --quality draft --output renders/draft.mp4`
  - `npx hyperframes render --quality standard --output renders/final.mp4`
- **Remotion** (chạy trong `engines/remotion/`):
  - `npx remotion render <composition-id> --props="<project>/props.resolved.json" --output ../../outputs/<ten>.mp4`

## 4. Quy tắc bắt buộc
1. **Không bao giờ final render khi chưa qua draft + verify frame.** Draft (CRF 28) nhanh; final chậm — phát hiện lỗi ở draft trước.
2. **Mọi render đều đi qua render queue của backend** để web UI thấy trạng thái.
3. Video tiếng Việt: chú ý font tiếng Việt và mốc thời gian karaoke từ `transcript.json`.
4. Khi hoàn thành final render, cập nhật `meta.json` (status="done", output="outputs/...").
5. **Logo không tự sinh**: chỉ lấy từ `assets/brand-logos/` hoặc watermark của Style Design.
6. **Style Design** (Màu + Font) và **Phong cách dựng** (Chất liệu + Chuyển động) hoạt động đồng thời, không đè nhau.
