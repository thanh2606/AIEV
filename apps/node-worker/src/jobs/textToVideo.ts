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
import { logger } from "../logger.js";
import { regenerateProjectCompositions } from "../compositionGenerator.js";

const MIN_SCRIPT_CHARS = 80;

export async function buildTextToVideo(id: string): Promise<{ projectId: string }> {
  logger.info(`[Job:TextToVideo] Bắt đầu quy trình dựng video từ bài viết cho session "${id}"...`);
  try {
    const meta = readTextToVideo(id);
    const dir = textToVideoDirOf(id);
    const script = scriptTextOf(meta).trim();

    if (script.length < MIN_SCRIPT_CHARS) {
      logger.warn(`[Job:TextToVideo] Kịch bản đọc quá ngắn (${script.length} ký tự)`);
      throw new Error(
        `Kịch bản đọc quá ngắn (${script.length} ký tự) - viết kịch bản trước khi dựng video.`,
      );
    }
    if (meta.projectId) {
      logger.info(`[Job:TextToVideo] Phiên "${id}" đã có project "${meta.projectId}", tiến hành làm mới compositions HTML...`);
      regenerateProjectCompositions(meta.projectId);
      return { projectId: meta.projectId };
    }

    // 1. Tổng hợp giọng đọc (TTS)
    logger.info(`[Job:TextToVideo] (Bước 1/3) Tổng hợp giọng đọc (TTS: ${meta.voice.engine || "gemini"}, voice: ${meta.voice.name})...`);
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

    logger.info(`[Job:TextToVideo] Tổng hợp giọng đọc hoàn tất (thời lượng: ${synth.durationSec.toFixed(1)}s)`);

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
    logger.info(`[Job:TextToVideo] (Bước 2/3) Chạy Transcribe lấy mốc thời gian từ voice.wav...`);
    const transcriptAbs = path.join(dir, "transcript.json");
    await transcribeVideo({
      videoAbs: wavAbs,
      outJsonAbs: transcriptAbs,
      language: "vi",
    });
    patchTextToVideo(id, { transcriptFile: toRepoRel(transcriptAbs) });
    logger.info(`[Job:TextToVideo] Transcribe hoàn tất, saved to ${transcriptAbs}`);

    // 3. Bàn giao cho pipeline Videos Project
    logger.info(`[Job:TextToVideo] (Bước 3/3) Bàn giao cho Videos Project...`);
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
      voice: "assets/voice.wav",
    };
    projectMeta.textToVideoId = id;
    writeMeta(summary.id, projectMeta);

    // Dynamic Rich Composition Generation
    regenerateProjectCompositions(summary.id);

    patchTextToVideo(id, { projectId: summary.id, status: "editing", error: null });
    logger.info(`[Job:TextToVideo] Đã hoàn thành dựng project "${summary.id}" từ session "${id}"!`);

    return { projectId: summary.id };
  } catch (err) {
    logger.error(`[Job:TextToVideo] Lỗi trong quy trình dựng video cho session "${id}":`, err);
    throw err;
  }
}
