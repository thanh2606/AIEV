import fs from "node:fs";
import path from "node:path";
import { repoRoot } from "./config.js";
import { readMeta, writeMeta, type SceneMeta } from "./meta.js";
import { ensureDir } from "./util.js";
import { logger } from "./logger.js";

const GRADIENTS = [
  "linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0284c7 100%)", // Indigo & Cyan
  "linear-gradient(135deg, #064e3b 0%, #0f172a 50%, #059669 100%)", // Emerald & Dark Teal
  "linear-gradient(135deg, #3b0764 0%, #1e1b4b 50%, #c026d3 100%)", // Purple & Magenta
  "linear-gradient(135deg, #451a03 0%, #1e1b4b 50%, #ea580c 100%)", // Sunset Amber
  "linear-gradient(135deg, #172554 0%, #311042 50%, #4f46e5 100%)", // Royal Blue & Violet
];

const HIGHLIGHT_WORDS = [
  "kinh tế", "việt nam", "phát triển", "tăng trưởng", "bứt phá",
  "đầu tư", "kết quả", "xuất khẩu", "giải ngân", "cao tốc",
  "bán dẫn", "năng lượng", "pháp lý", "động lực", "doanh nghiệp"
];

export interface ScaffoldOptions {
  sceneId: string;
  text: string;
  durationSec: number;
  width?: number;
  height?: number;
  colorIndex?: number;
}

export function scaffoldRichSceneHtml(opts: ScaffoldOptions): string {
  const { sceneId, text, durationSec, width = 1080, height = 1920, colorIndex = 0 } = opts;
  const bgGradient = GRADIENTS[colorIndex % GRADIENTS.length];
  
  // Format text into HTML with word highlights
  const words = (text || sceneId).split(/\s+/);
  const formattedText = words
    .map((word) => {
      const clean = word.toLowerCase().replace(/[^a-z0-9àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/gi, "");
      const isKeyword = HIGHLIGHT_WORDS.some((kw) => clean.includes(kw));
      if (isKeyword) {
        return `<span class="word highlight">${escapeHtml(word)}</span>`;
      }
      return `<span class="word">${escapeHtml(word)}</span>`;
    })
    .join(" ");

  const numWords = words.length;
  const staggerTime = Math.min(0.08, (durationSec * 0.6) / Math.max(1, numWords));

  return `<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=${width}, height=${height}" />
    <title>${escapeHtml(sceneId)}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      html, body {
        width: ${width}px;
        height: ${height}px;
        overflow: hidden;
        background: #090a0f;
        font-family: 'Inter', system-ui, sans-serif;
        color: #ffffff;
      }
      #root {
        position: relative;
        width: ${width}px;
        height: ${height}px;
        overflow: hidden;
        background: ${bgGradient};
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 80px 60px;
      }
      .bg-orb {
        position: absolute;
        width: 600px;
        height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(56, 189, 248, 0.25) 0%, rgba(0, 0, 0, 0) 70%);
        top: 20%;
        left: 10%;
        pointer-events: none;
        filter: blur(40px);
      }
      .glass-card {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 960px;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 36px;
        padding: 64px 48px;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 36px;
      }
      .badge {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 10px 24px;
        background: rgba(56, 189, 248, 0.15);
        border: 1px solid rgba(56, 189, 248, 0.35);
        border-radius: 999px;
        font-family: 'Outfit', sans-serif;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #38bdf8;
      }
      .dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #38bdf8;
        box-shadow: 0 0 12px #38bdf8;
      }
      .headline {
        font-family: 'Outfit', sans-serif;
        font-size: 52px;
        font-weight: 800;
        line-height: 1.35;
        color: #f8fafc;
        word-spacing: 4px;
      }
      .word {
        display: inline-block;
        margin: 0 4px;
      }
      .word.highlight {
        color: #fbbf24;
        font-weight: 900;
        text-shadow: 0 0 20px rgba(251, 191, 36, 0.4);
      }
    </style>
  </head>
  <body>
    <div
      id="root"
      data-composition-id="${escapeHtml(sceneId)}"
      data-start="0"
      data-duration="${durationSec}"
      data-width="${width}"
      data-height="${height}"
    >
      <div class="bg-orb"></div>
      <div class="glass-card">
        <div class="badge">
          <span class="dot"></span>
          <span>${escapeHtml(sceneId.replace("_", " ").toUpperCase())}</span>
        </div>
        <div class="headline">
          ${formattedText}
        </div>
      </div>
    </div>
    <script>
      window.__timelines = window.__timelines || {};
      const tl = gsap.timeline({ paused: true });

      // Ambient background drift
      tl.to(".bg-orb", { scale: 1.3, x: 50, y: -30, duration: ${durationSec}, ease: "none" }, 0);

      // Glass card entrance
      tl.from(".glass-card", {
        opacity: 0,
        y: 60,
        scale: 0.92,
        duration: 0.8,
        ease: "back.out(1.4)",
      }, 0);

      // Staggered word animation
      tl.from(".word", {
        opacity: 0,
        y: 20,
        scale: 1.15,
        duration: 0.4,
        stagger: ${staggerTime.toFixed(3)},
        ease: "power2.out",
      }, 0.2);

      // Highlight glow effect
      tl.to(".word.highlight", {
        scale: 1.08,
        duration: 0.4,
        yoyo: true,
        repeat: 1,
        ease: "sine.inOut",
      }, 0.6);

      window.__timelines["${escapeHtml(sceneId)}"] = tl;
    </script>
  </body>
</html>
`;
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

export function regenerateProjectCompositions(projectId: string): void {
  logger.info(`[CompositionGenerator] Bắt đầu tạo lại giao diện HTML compositions cho project "${projectId}"...`);
  const projectDir = path.join(repoRoot, "video-projects", projectId);
  const meta = readMeta(projectId);
  const transcriptPath = path.join(projectDir, "assets", "transcript.json");

  ensureDir(path.join(projectDir, "compositions"));
  ensureDir(path.join(projectDir, "renders"));

  let segments: { text: string; start: number; end: number }[] = [];

  if (fs.existsSync(transcriptPath)) {
    try {
      const transcriptData = JSON.parse(fs.readFileSync(transcriptPath, "utf-8"));
      if (Array.isArray(transcriptData.segments) && transcriptData.segments.length > 0) {
        segments = transcriptData.segments.map((s: any) => ({
          text: String(s.text || "").trim(),
          start: parseFloat(s.start || 0),
          end: parseFloat(s.end || 5),
        }));
      }
    } catch (e) {
      logger.warn(`[CompositionGenerator] Không thể đọc transcript.json tại ${transcriptPath}:`, e);
    }
  }

  const updatedScenes: SceneMeta[] = [];

  if (segments.length > 0) {
    for (let i = 0; i < segments.length; i++) {
      const seg = segments[i];
      const sceneId = `scene_${i + 1}`;
      const duration = Math.max(3, parseFloat((seg.end - seg.start).toFixed(2)));
      const compRel = `compositions/${sceneId}.html`;
      const compAbs = path.join(projectDir, compRel);

      const htmlContent = scaffoldRichSceneHtml({
        sceneId,
        text: seg.text,
        durationSec: duration,
        width: meta.width || 1080,
        height: meta.height || 1920,
        colorIndex: i,
      });

      fs.writeFileSync(compAbs, htmlContent, "utf8");

      updatedScenes.push({
        id: sceneId,
        src: compRel,
        render: `renders/${sceneId}.mp4`,
        duration,
        durationInFrames: Math.round(duration * (meta.fps || 30)),
      });
    }
  } else {
    // Fallback if no transcript segments available
    const sceneCount = Math.max(3, meta.scenes?.length || 5);
    for (let i = 0; i < sceneCount; i++) {
      const sceneId = `scene_${i + 1}`;
      const duration = 5;
      const compRel = `compositions/${sceneId}.html`;
      const compAbs = path.join(projectDir, compRel);

      const htmlContent = scaffoldRichSceneHtml({
        sceneId,
        text: `${meta.name || "Video"} - Phân cảnh ${i + 1}`,
        durationSec: duration,
        width: meta.width || 1080,
        height: meta.height || 1920,
        colorIndex: i,
      });

      fs.writeFileSync(compAbs, htmlContent, "utf8");

      updatedScenes.push({
        id: sceneId,
        src: compRel,
        render: `renders/${sceneId}.mp4`,
        duration,
        durationInFrames: Math.round(duration * (meta.fps || 30)),
      });
    }
  }

  meta.scenes = updatedScenes;
  meta.updatedAt = new Date().toISOString();
  writeMeta(projectId, meta);

  logger.info(`[CompositionGenerator] Đã tạo lại ${updatedScenes.length} scenes HTML compositions thành công cho project "${projectId}"!`);
}
