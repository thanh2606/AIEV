import fs from "node:fs";
import path from "node:path";
import express from "express";
import dotenv from "dotenv";
import { planAgent } from "./planner.js";

// Nạp .env từ repo root
const repoRoot = path.resolve(path.dirname(new URL(import.meta.url).pathname), "../../../");
dotenv.config({ path: path.join(repoRoot, ".env") });

const PORT = Number(process.env.NODE_WORKER_PORT || 6870);
const app = express();
app.use(express.json({ limit: "10mb" }));

// ---- Health ----
app.get("/health", (_req, res) => {
  res.json({ status: "ok", service: "node-worker", version: "0.1.0" });
});

// ---- Internal: Claude AI Planner (single-turn) ----
/**
 * POST /internal/agent/plan
 *
 * Claude suy luận ĐÚNG 1 TURN duy nhất → trả về JSON JobSchedulePlan.
 * Không chạy Bash, không đợi render. Pure data.
 */
app.post("/internal/agent/plan", async (req, res) => {
  try {
    const { sessionId, projectId, message, model, effort } = req.body;

    if (!message) {
      res.status(400).json({ error: "Missing message" });
      return;
    }

    const result = await planAgent({
      sessionId,
      projectId,
      message,
      model,
      effort,
      repoRoot,
    });

    res.json(result);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    console.error("[node-worker] Plan error:", errMsg);
    res.status(500).json({ error: errMsg });
  }
});

// ---- Internal: Interrupt ----
app.post("/internal/agent/interrupt", async (req, res) => {
  const { sessionId } = req.body;
  // TODO: implement interrupt logic
  console.log(`[node-worker] Interrupt requested for session ${sessionId}`);
  res.json({ interrupted: true });
});

// ---- Text-to-Video AI Scripting ----
import { generateScript } from "./scripting.js";

app.post("/api/text-to-video/:id/script", async (req, res) => {
  try {
    const { id } = req.params;
    const { targetSeconds = 60, model } = req.body;
    const meta = await generateScript(repoRoot, id, Number(targetSeconds), model);
    res.json(meta);
  } catch (err) {
    const errMsg = err instanceof Error ? err.message : String(err);
    console.error(`[node-worker] Script generation error for ${req.params.id}:`, errMsg);
    res.status(500).json({ error: { message: errMsg } });
  }
});

app.listen(PORT, () => {
  console.log(`[node-worker] Claude AI Planner chạy tại http://localhost:${PORT}`);
  console.log(`[node-worker] Repo root: ${repoRoot}`);
});
