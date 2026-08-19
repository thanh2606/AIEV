import fs from "node:fs";
import path from "node:path";
import { repoRoot } from "../config.js";
import { readMeta, writeMeta, type SceneMeta } from "../meta.js";
import { ensureDir, execFileCapture, hyperframesCli } from "../util.js";
import { logger } from "../logger.js";
import { scaffoldRichSceneHtml } from "../compositionGenerator.js";

export interface SceneRenderInput {
  projectId: string;
  sceneId?: string;
  quality: "draft" | "standard";
}

function scaffoldSceneHtml(sceneId: string, width = 1080, height = 1920): string {
  const matchNum = sceneId.match(/\d+/);
  const idx = matchNum ? parseInt(matchNum[0], 10) - 1 : 0;
  return scaffoldRichSceneHtml({
    sceneId,
    text: `Phân cảnh ${sceneId.replace("_", " ")}`,
    durationSec: 5,
    width,
    height,
    colorIndex: idx,
  });
}

export async function renderScene(input: SceneRenderInput): Promise<void> {
  const { projectId, sceneId, quality } = input;
  const draft = quality === "draft";
  const projectDir = path.join(repoRoot, "video-projects", projectId);
  const meta = readMeta(projectId);

  let scenes: SceneMeta[] = (meta.scenes ?? []).filter(
    (s): s is SceneMeta => typeof s.src === "string" && s.src.length > 0,
  );
  if (sceneId) {
    let found = scenes.find((s) => s.id === sceneId);
    if (!found) {
      logger.info(`[SceneRender] Scene "${sceneId}" chưa có trong meta.json của ${projectId}, tự động tạo scaffold...`);
      const compRel = `compositions/${sceneId}.html`;
      const compAbs = path.join(projectDir, compRel);
      ensureDir(path.dirname(compAbs));
      if (!fs.existsSync(compAbs)) {
        fs.writeFileSync(compAbs, scaffoldSceneHtml(sceneId, meta.width, meta.height), "utf8");
      }
      found = {
        id: sceneId,
        src: compRel,
        render: `renders/${sceneId}.mp4`,
        duration: 5,
      };
      meta.scenes = meta.scenes ?? [];
      meta.scenes.push(found);
      writeMeta(projectId, meta);
    }
    scenes = [found];
  }
  if (!scenes.length) {
    throw new Error("meta.json không có scene nào có `src` để render");
  }

  ensureDir(path.join(projectDir, "renders"));

  for (let i = 0; i < scenes.length; i++) {
    const scene = scenes[i];
    const finalRel =
      typeof scene.render === "string" && scene.render ? scene.render : `renders/${scene.id}.mp4`;
    const outRel = draft ? finalRel.replace(/\.mp4$/i, ".draft.mp4") : finalRel;
    ensureDir(path.dirname(path.join(projectDir, outRel)));

    logger.info(`[SceneRender] Project ${projectId} - Scene ${scene.id} (${quality})...`);

    const args = [
      hyperframesCli(),
      "render",
      "-c",
      String(scene.src),
      "--quality",
      quality,
      "--output",
      outRel,
    ];

    await execFileCapture(process.execPath, args, { cwd: projectDir, timeoutMs: 300_000 });

    const outAbs = path.join(projectDir, outRel);
    if (!fs.existsSync(outAbs)) {
      throw new Error(`Render xong nhưng không thấy file ${outRel}`);
    }
  }
}
