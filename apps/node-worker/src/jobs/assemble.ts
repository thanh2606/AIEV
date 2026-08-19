import fs from "node:fs";
import path from "node:path";
import { paths, repoRoot } from "../config.js";
import {
  briefOf,
  projectDirOf,
  readMeta,
  writeMeta,
  type ProjectMeta,
  type SceneMeta,
} from "../meta.js";
import { getVideoStyle } from "../videoStyles.js";
import { ensureDir, execFileCapture, remotionCli } from "../util.js";
import { logger } from "../logger.js";

export interface AssembleInput {
  projectId: string;
  quality: "draft" | "final";
}

function escapeRegExp(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function nextVersion(projectId: string): number {
  let max = 0;
  if (fs.existsSync(paths.outputsDir)) {
    const re = new RegExp(`^${escapeRegExp(projectId)}-v(\\d+)\\.mp4$`, "i");
    for (const name of fs.readdirSync(paths.outputsDir)) {
      const m = re.exec(name);
      if (m) max = Math.max(max, Number(m[1]));
    }
  }
  return max + 1;
}

export async function assembleVideo(input: AssembleInput): Promise<{ outputPath: string }> {
  const { projectId, quality } = input;
  const draft = quality === "draft";
  const projectDir = projectDirOf(projectId);
  const meta = readMeta(projectId);

  logger.info(`[Assemble] Bắt đầu lắp ráp video cho project ${projectId} (${quality})...`);

  // 1. Stage asset vào Remotion staging
  const stagingId = `vid-${projectId}`;
  const stagingAbs = path.join(paths.stagingDir, stagingId);
  fs.rmSync(stagingAbs, { recursive: true, force: true });
  ensureDir(stagingAbs);

  const keyOf = (s: string): string => (process.platform === "win32" ? s.toLowerCase() : s);
  const staged = new Map<string, string>();
  const flatOwner = new Map<string, { rel: string; srcKey: string; publicRel: string }>();

  const stage = (relFromProject: string): string => {
    let rel = relFromProject;
    const prefix = `video-projects/${projectId}/`;
    if (rel.startsWith(prefix)) {
      rel = rel.slice(prefix.length);
    }
    const srcAbs = path.isAbsolute(rel) ? rel : path.join(projectDir, rel);
    const resolved = path.resolve(srcAbs);
    const projAbs = path.resolve(projectDir);
    const repoAbs = path.resolve(repoRoot);

    if (!resolved.startsWith(projAbs + path.sep) && !resolved.startsWith(repoAbs + path.sep)) {
      throw new Error(
        `Đường dẫn asset "${relFromProject}" nằm ngoài project ${projectId} - từ chối stage`,
      );
    }
    const srcKey = keyOf(resolved);
    const cached = staged.get(srcKey);
    if (cached) return cached;
    if (!fs.existsSync(srcAbs)) {
      throw new Error(`Thiếu asset "${relFromProject}" trong project ${projectId}`);
    }
    const flat = relFromProject.split(/[\\/]+/).join("__");
    const dstAbs = path.join(stagingAbs, flat);
    const publicRel = `staging/${stagingId}/${flat}`;

    const owner = flatOwner.get(keyOf(flat));
    if (owner) {
      if (owner.srcKey === srcKey) {
        staged.set(srcKey, owner.publicRel);
        return owner.publicRel;
      }
      throw new Error(`Xung đột tên khi stage: "${relFromProject}"`);
    }

    try {
      fs.linkSync(srcAbs, dstAbs);
    } catch {
      fs.copyFileSync(srcAbs, dstAbs);
    }
    staged.set(srcKey, publicRel);
    flatOwner.set(keyOf(flat), { rel: relFromProject, srcKey, publicRel });
    return publicRel;
  };

  const props = JSON.parse(JSON.stringify(meta)) as ProjectMeta;

  for (const scene of props.scenes ?? []) {
    if (!scene.durationInFrames) {
      const durSec = (typeof scene.duration === "number" && scene.duration > 0) ? scene.duration : 5;
      scene.durationInFrames = Math.round(durSec * (props.fps || 30));
    }
    if (typeof scene.src === "string" && scene.src) {
      const finalRel =
        typeof scene.render === "string" && scene.render
          ? scene.render
          : `renders/${scene.id}.mp4`;
      const draftRel = finalRel.replace(/\.mp4$/i, ".draft.mp4");
      let renderRel = draft ? draftRel : finalRel;
      if (!fs.existsSync(path.join(projectDir, renderRel))) {
        const alt = draft ? finalRel : draftRel;
        if (fs.existsSync(path.join(projectDir, alt))) {
          renderRel = alt;
        } else {
          throw new Error(`Scene "${scene.id}" chưa được render (${renderRel}).`);
        }
      }
      scene.render = stage(renderRel);
    } else if (typeof scene.render === "string" && scene.render) {
      if (fs.existsSync(path.join(projectDir, scene.render))) {
        scene.render = stage(scene.render);
      }
    }
    if (typeof scene.srcVideo === "string" && scene.srcVideo) {
      scene.srcVideo = stage(scene.srcVideo);
    }
    if (typeof scene.srcImage === "string" && scene.srcImage) {
      scene.srcImage = stage(scene.srcImage);
    }
  }

  // Watermark
  (props as ProjectMeta & { watermark?: unknown }).watermark = null;

  if (props.audio) {
    if (typeof props.audio.voice === "string" && props.audio.voice) {
      props.audio.voice = stage(props.audio.voice);
    }
    for (const sfx of props.audio.sfx ?? []) {
      if (typeof sfx.file === "string" && sfx.file) sfx.file = stage(sfx.file);
    }
    const music = props.audio.music;
    if (music && typeof music.file === "string" && music.file) {
      music.file = stage(music.file);
    }
  }

  // 2. props.resolved.json
  const propsAbs = path.join(projectDir, "props.resolved.json");
  fs.writeFileSync(propsAbs, JSON.stringify(props, null, 2) + "\n", "utf8");

  // 3. Remotion render
  ensureDir(paths.outputsDir);
  const outName = draft ? `${projectId}-draft.mp4` : `${projectId}-v${nextVersion(projectId)}.mp4`;
  const outAbs = path.join(paths.outputsDir, outName);

  const args = [
    remotionCli(),
    "render",
    "Assemble",
    `--props=${propsAbs}`,
    `--output=${outAbs}`,
    ...(draft ? ["--crf", "28", "--x264-preset", "veryfast"] : []),
  ];

  await execFileCapture(process.execPath, args, { cwd: paths.remotionDir, timeoutMs: 600_000 });

  if (!fs.existsSync(outAbs)) {
    throw new Error(`Remotion render xong nhưng không thấy file ${outName}`);
  }

  const outputRel = `outputs/${outName}`;
  if (!draft) {
    const freshMeta = readMeta(projectId);
    freshMeta.status = "done";
    freshMeta.output = outputRel;
    writeMeta(projectId, freshMeta);
  }

  logger.info(`[Assemble] Lắp ráp xong video: ${outputRel}`);
  return { outputPath: outputRel };
}
