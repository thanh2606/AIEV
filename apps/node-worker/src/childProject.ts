import fs from "node:fs";
import path from "node:path";
import { paths, repoRoot } from "./config.js";
import {
  briefOf,
  defaultBrief,
  normTags,
  projectAssetsDirOf,
  projectDirOf,
  projectSummaryOf,
  readMeta,
  writeMeta,
  type Brief,
  type ProjectMeta,
  type ProjectSummary,
  type SceneMeta,
} from "./meta.js";
import { HttpError, ensureDir, fileKind, nowIso, toKebabAscii } from "./util.js";

export function uniqueProjectId(base: string): string {
  const root = toKebabAscii(base) || "project";
  let id = root;
  for (let n = 2; fs.existsSync(projectDirOf(id)); n++) id = `${root}-${n}`;
  return id;
}

export function scaffoldProjectFiles(
  id: string,
  name: string,
  width: number,
  height: number,
): void {
  const dir = projectDirOf(id);
  ensureDir(dir);
  ensureDir(path.join(dir, "compositions"));
  ensureDir(path.join(dir, "assets"));
  ensureDir(path.join(dir, "renders"));

  fs.writeFileSync(path.join(dir, "index.html"), scaffoldIndexHtml(id, name, width, height), "utf8");
  fs.writeFileSync(
    path.join(dir, "hyperframes.json"),
    JSON.stringify(
      {
        $schema: "https://hyperframes.heygen.com/schema/hyperframes.json",
        registry: "https://raw.githubusercontent.com/heygen-com/hyperframes/main/registry",
        paths: {
          blocks: "compositions",
          components: "compositions/components",
          assets: "assets",
        },
        media: { autoProxy: true },
      },
      null,
      2,
    ) + "\n",
    "utf8",
  );
}

export function scaffoldIndexHtml(
  id: string,
  name: string,
  width: number,
  height: number,
): string {
  return `<!doctype html>
<html lang="vi">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=${width}, height=${height}" />
    <title>${escapeHtml(name)}</title>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      html, body {
        width: ${width}px;
        height: ${height}px;
        overflow: hidden;
        background: #101113;
        font-family: Inter, system-ui, sans-serif;
      }
      #root {
        position: relative;
        width: ${width}px;
        height: ${height}px;
        overflow: hidden;
        background: #101113;
      }
      .placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
      }
      .placeholder h1 { font-size: 64px; font-weight: 800; text-align: center; padding: 0 48px; }
    </style>
  </head>
  <body>
    <div
      id="root"
      data-composition-id="${escapeHtml(id)}"
      data-start="0"
      data-duration="5"
      data-width="${width}"
      data-height="${height}"
    >
      <div class="placeholder"><h1>${escapeHtml(name)}</h1></div>
    </div>
    <script>
      window.__timelines = window.__timelines || {};
      const tl = gsap.timeline({ paused: true });
      tl.to({}, { duration: 5 }, 0);
      window.__timelines["${escapeHtml(id)}"] = tl;
    </script>
  </body>
</html>
`;
}

export function escapeHtml(s: string): string {
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function bringFile(srcAbs: string, dstAbs: string): void {
  ensureDir(path.dirname(dstAbs));
  if (fs.existsSync(dstAbs)) return;
  if (fileKind(srcAbs) === "other") {
    fs.copyFileSync(srcAbs, dstAbs);
    return;
  }
  try {
    fs.linkSync(srcAbs, dstAbs);
  } catch {
    fs.copyFileSync(srcAbs, dstAbs);
  }
}

function resolveInside(baseDir: string, rel: string, what: string): string {
  const root = path.resolve(baseDir);
  const abs = path.resolve(root, rel);
  if (abs !== root && !abs.startsWith(root + path.sep)) {
    throw new HttpError(400, "PATH_OUTSIDE", `${what} "${rel}" nằm ngoài thư mục cho phép`);
  }
  return abs;
}

export interface CreateChildProjectInput {
  parentId: string | null;
  name: string;
  width: number;
  height: number;
  fps: number;
  brief: Partial<Brief>;
  scenes?: SceneMeta[];
  audio?: ProjectMeta["audio"];
  copyAssets?: string[];
  copyFiles?: { srcAbs: string; destRel: string }[];
  copyCompositions?: boolean;
}

export function createChildProject(input: CreateChildProjectInput): ProjectSummary {
  const parentMeta = input.parentId === null ? null : readMeta(input.parentId);

  const name = input.name.trim();
  if (!name) throw new HttpError(400, "INVALID_NAME", "Thiếu name cho project con");

  const id = uniqueProjectId(name);
  scaffoldProjectFiles(id, name, input.width, input.height);

  const childAssetsDir = projectAssetsDirOf(id);

  for (const f of input.copyFiles ?? []) {
    const srcAbs = path.resolve(f.srcAbs);
    if (!fs.existsSync(srcAbs) || !fs.statSync(srcAbs).isFile()) continue;
    const dstAbs = resolveInside(childAssetsDir, f.destRel, "Đích asset");
    bringFile(srcAbs, dstAbs);
  }

  const brief: Brief = {
    ...defaultBrief(),
    ...(parentMeta ? briefOf(parentMeta) : {}),
  };
  for (const [k, v] of Object.entries(input.brief)) {
    if (v === undefined) continue;
    (brief as unknown as Record<string, unknown>)[k] = v;
  }

  const meta: ProjectMeta = {
    id,
    name,
    width: input.width,
    height: input.height,
    fps: input.fps,
    status: "draft",
    parentId: input.parentId,
    brief,
    scenes: (input.scenes ?? []).map((s) => ({ ...s })),
    audio: input.audio ? JSON.parse(JSON.stringify(input.audio)) : { voice: null, sfx: [] },
    output: null,
    tags: normTags(parentMeta?.tags),
    createdAt: nowIso(),
    updatedAt: nowIso(),
  };
  writeMeta(id, meta);

  const summary = projectSummaryOf(id);
  if (!summary) {
    throw new HttpError(500, "CHILD_CREATE_FAILED", `Không đọc lại được project con "${id}"`);
  }
  return summary;
}
