import fs from "node:fs";
import path from "node:path";
import { repoRoot } from "../config.js";
import { createChildProject } from "../childProject.js";
import { readMeta, writeMeta } from "../meta.js";
import { transcribeVideo } from "../transcribe.js";
import { synthScript } from "../tts.js";
import {
  patchTextToVideo,
  readTextToVideo,
  scriptTextOf,
  textToVideoDirOf,
} from "../textToVideoMeta.js";
import { ensureDir, toRepoRel } from "../util.js";

const MIN_SCRIPT_CHARS = 80;

export async function buildTextToVideo(id: string): Promise<{ projectId: string }> {
  const meta = readTextToVideo(id);
  const dir = textToVideoDirOf(id);
  const script = scriptTextOf(meta).trim();

  if (script.length < MIN_SCRIPT_CHARS) {
    throw new Error(
      `Kịch bản đọc quá ngắn (${script.length} ký tự) - viết kịch bản trước khi dựng video.`,
    );
  }
  if (meta.projectId) {
    throw new Error(
      `Phiên này đã tạo project "${meta.projectId}" rồi - xóa project đó hoặc tạo phiên mới.`,
    );
  }

  // 1. Tổng hợp giọng đọc (TTS)
  patchTextToVideo(id, { status: "voicing", error: null });
  const voiceDir = path.join(dir, "voice");
  ensureDir(voiceDir);
  const wavAbs = path.join(dir, "voice.wav");

  const chunks = meta.script.map((c) => c.text.trim()).filter(Boolean);
  const synth = await synthScript({
    chunks,
    engine: meta.voice.engine,
    speed: meta.voice.speed,
    voice: meta.voice.name,
    model: meta.voice.model,
    style: meta.voice.style,
    language: meta.voice.language,
    workDir: voiceDir,
    outWavAbs: wavAbs,
  });

  const scriptWithDurations = meta.script.map((c, i) => ({
    ...c,
    durationSec: synth.chunkDurations[i] ?? null,
  }));
  patchTextToVideo(id, {
    voiceFile: toRepoRel(wavAbs),
    voiceDurationSec: synth.durationSec,
    script: scriptWithDurations,
  });

  // 2. Transcribe (Whisper) để lấy mốc thời gian từng từ
  const transcriptAbs = path.join(dir, "transcript.json");
  await transcribeVideo({
    videoAbs: wavAbs,
    outJsonAbs: transcriptAbs,
    language: "vi",
  });
  patchTextToVideo(id, { transcriptFile: toRepoRel(transcriptAbs) });

  // 3. Bàn giao cho pipeline Videos Project
  patchTextToVideo(id, { status: "building" });
  const fresh = readTextToVideo(id);

  const summary = createChildProject({
    parentId: null,
    name: fresh.name,
    width: fresh.output.width,
    height: fresh.output.height,
    fps: fresh.output.fps,
    brief: {
      ...fresh.brief,
      styleId: fresh.output.styleId,
      autoCut: false,
    },
    copyFiles: [
      { srcAbs: wavAbs, destRel: "voice.wav" },
      { srcAbs: transcriptAbs, destRel: "transcript.json" },
    ],
  });

  const projectMeta = readMeta(summary.id);
  projectMeta.audio = {
    ...(projectMeta.audio ?? { voice: null, sfx: [] }),
    voice: `video-projects/${summary.id}/assets/voice.wav`,
  };
  projectMeta.textToVideoId = id;
  writeMeta(summary.id, projectMeta);

  patchTextToVideo(id, { projectId: summary.id, status: "editing", error: null });

  return { projectId: summary.id };
}
