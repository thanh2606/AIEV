import fs from "node:fs";
import path from "node:path";
import express from "express";
import dotenv from "dotenv";

// Nạp .env từ repo root
const repoRoot = path.resolve(path.dirname(new URL(import.meta.url).pathname), "../../../");
dotenv.config({ path: path.join(repoRoot, ".env") });

const PORT = Number(process.env.NODE_WORKER_PORT || 6870);
const app = express();
app.use(express.json({ limit: "50mb" }));

// ---- Health ----
app.get("/health", (_req, res) => {
  res.json({ status: "ok", service: "node-worker", version: "0.1.0" });
});

// ==========================================
// 1. NHÓM CLAUDE AI AGENT (Pure Data)
// ==========================================
import { planAgent } from "./planner.js";
import { generateScript } from "./scripting.js";
import { publishAgent, clipsSuggestAgent, autoCutPlanAgent, translateAgent } from "./agent.js";
import { synthPreviewWav, applySpeedToWav } from "./tts.js";
import { synthLocalPreviewWav, probeLocalEngine, listLocalVoices } from "./ttsLocal.js";
import { normTtsSpeed } from "./textToVideoMeta.js";
import { createClonedVoice, getClonedVoice, patchClonedVoice, removeClonedVoice, listClonedVoices } from "./voiceStore.js";
import { previewTextFor } from "./ttsTypes.js";
import { transcribeVideo } from "./transcribe.js";

// 1.1 Suy luận Plan (Task Schedule)
app.post("/internal/agent/plan", async (req, res) => {
  try {
    const { sessionId, projectId, message, model, effort } = req.body;
    if (!message) return res.status(400).json({ error: "Missing message" });
    const result = await planAgent({ sessionId, projectId, message, model, effort, repoRoot });
    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 1.2 Dừng Agent đang chạy
app.post("/internal/agent/interrupt", async (req, res) => {
  const { sessionId } = req.body;
  console.log(`[node-worker] Interrupt requested for session ${sessionId}`);
  res.json({ interrupted: true });
});

// 1.3 Chat (Multi-turn hoặc Agent Tool Use)
app.post("/internal/agent/chat", async (req, res) => {
  res.status(501).json({ error: "Not implemented - Chat uses planner logic" });
});

// 1.4 Edit Project
app.post("/internal/agent/edit", async (req, res) => {
  res.status(501).json({ error: "Not implemented - Edit uses planner logic" });
});

// 1.5 Duyệt & Gửi Ghi chú (Review)
app.post("/internal/agent/review", async (req, res) => {
  res.status(501).json({ error: "Not implemented - Review uses planner logic" });
});

// 1.6 Viết kịch bản (Scripting)
app.post("/internal/agent/script", async (req, res) => {
  try {
    const { id, targetSeconds = 60, model } = req.body;
    if (!id) return res.status(400).json({ error: "Missing project id" });
    const meta = await generateScript(repoRoot, id, Number(targetSeconds), model);
    res.json(meta);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 1.7 Dịch phụ đề (Translate)
app.post("/internal/agent/translate", async (req, res) => {
  try {
    const { cuesText, targetLang, model } = req.body;
    if (!cuesText || !targetLang) return res.status(400).json({ error: "Missing cuesText or targetLang" });
    const result = await translateAgent({ repoRoot, cuesText, targetLang, model });
    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 1.8 Soạn metadata đăng bài (Publish)
app.post("/internal/agent/publish", async (req, res) => {
  try {
    const { projectId, projectName, sourceDescription, durationSec, style, timedText, platforms, model } = req.body;
    if (!projectId || !platforms) return res.status(400).json({ error: "Missing payload" });
    const result = await publishAgent({ repoRoot, projectId, projectName, sourceDescription, durationSec, style, timedText, platforms, model });
    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 1.9 Chọn đoạn AutoCut (AutoCut Plan)
app.post("/internal/agent/autocut-plan", async (req, res) => {
  try {
    const { transcriptText, model } = req.body;
    if (!transcriptText) return res.status(400).json({ error: "Missing transcriptText" });
    const result = await autoCutPlanAgent({ repoRoot, transcriptText, model });
    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 1.10 Gợi ý đoạn cắt Short (Clips Suggest)
app.post("/internal/agent/clips-suggest", async (req, res) => {
  try {
    const { projectId, projectName, sourceDescription, durationSec, timedText, count, minSec, maxSec, model } = req.body;
    if (!projectId || !timedText) return res.status(400).json({ error: "Missing payload" });
    const result = await clipsSuggestAgent({ repoRoot, projectId, projectName, sourceDescription, durationSec, timedText, count, minSec, maxSec, model });
    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// ==========================================
// 2. NHÓM TEXT-TO-SPEECH (TTS & Voices)
// ==========================================

// 2.1 Nghe thử giọng TTS
app.post("/internal/tts/preview", async (req, res) => {
  try {
    const { engine, voice, model, style, language, speed, text } = req.body;
    if (!voice || !text) return res.status(400).json({ error: "Missing voice or text" });

    const synth = engine === "vieneu"
      ? await synthLocalPreviewWav({ text, voice })
      : await synthPreviewWav({
          text,
          voice,
          model: model || null,
          style: style || "",
          language: language || undefined,
        });

    const spd = normTtsSpeed(speed);
    const wav = await applySpeedToWav(synth.wav, spd);
    const durationSec = synth.durationSec / spd;

    // Trả base64 về cho Laravel
    res.json({
      audioBase64: wav.toString("base64"),
      durationSec,
      modelUsed: synth.model,
    });
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 2.2 Nghe thử câu lồng tiếng (Dub Preview)
app.post("/internal/tts/dub-preview", async (req, res) => {
  res.status(501).json({ error: "Not implemented" });
});

// 2.3 Nhân bản giọng (VieNeu Clone)
app.post("/internal/tts/clone", async (req, res) => {
  try {
    const { name, gender, note, srcAbs } = req.body;
    if (!srcAbs) return res.status(400).json({ error: "Missing srcAbs" });

    const entry = await createClonedVoice({ name, gender, note, srcAbs });
    res.json(entry);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 2.3.1 Cập nhật giọng nhân bản
app.patch("/internal/tts/clone/:id", async (req, res) => {
  try {
    const entry = patchClonedVoice(req.params.id, req.body);
    res.json(entry);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 2.3.2 Xóa giọng nhân bản
app.delete("/internal/tts/clone/:id", async (req, res) => {
  try {
    removeClonedVoice(req.params.id);
    res.json({ id: req.params.id });
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 2.3.3 Lấy danh sách giọng nhân bản
app.get("/internal/tts/clone", async (req, res) => {
  try {
    res.json(listClonedVoices());
  } catch (err) {
    res.status(500).json({ error: String(err) });
  }
});

// 2.4 Nghe thử giọng nhân bản
app.post("/internal/tts/clone-preview", async (req, res) => {
  try {
    const { id, uiLang } = req.body;
    if (!id) return res.status(400).json({ error: "Missing id" });

    const voice = getClonedVoice(id);
    if (!voice) return res.status(404).json({ error: `Voice not found: ${id}` });

    const text = previewTextFor(uiLang);
    const { wav, durationSec, model } = await synthLocalPreviewWav({ text, voice: id });

    res.json({
      audioBase64: wav.toString("base64"),
      durationSec,
      modelUsed: model,
    });
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// 2.5 Dò trạng thái VieNeu Engine
app.get("/internal/tts/engines", async (req, res) => {
  try {
    const status = await probeLocalEngine(req.query.refresh === "1");
    res.json(status);
  } catch (err) {
    res.status(500).json({ error: String(err) });
  }
});

// 2.6 Lấy danh sách giọng VieNeu (preset + cloned)
app.get("/internal/tts/voices", async (req, res) => {
  try {
    const voices = await listLocalVoices();
    res.json(voices);
  } catch (err) {
    res.status(500).json({ error: String(err) });
  }
});

// ==========================================
// 3. NHÓM SPEECH-TO-TEXT (Transcribe)
// ==========================================

// 3.1 Bóc lời video/audio (Whisper / Soniox)
app.post("/internal/transcribe", async (req, res) => {
  try {
    const { videoAbs, outJsonAbs, language } = req.body;
    if (!videoAbs || !outJsonAbs) {
      return res.status(400).json({ error: "Missing videoAbs or outJsonAbs" });
    }

    const result = await transcribeVideo({
      videoAbs,
      outJsonAbs,
      language: language || "auto",
      onLog: (line) => console.log(`[transcribe] ${line}`)
    });

  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

import { buildTextToVideo } from "./jobs/textToVideo.js";

// 3.2 Dựng Text To Video (TTS + Transcribe + Create Project)
app.post("/internal/text-to-video/build", async (req, res) => {
  try {
    const { id } = req.body;
    if (!id) return res.status(400).json({ error: "Missing id" });

    const result = await buildTextToVideo(id);
    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    res.status(500).json({ error: errMsg });
  }
});

// ==========================================
app.listen(PORT, () => {
  console.log(`[node-worker] Service chạy tại http://localhost:${PORT}`);
  console.log(`[node-worker] Repo root: ${repoRoot}`);
});
