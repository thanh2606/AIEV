import fs from "node:fs";
import path from "node:path";
import { query } from "@anthropic-ai/claude-agent-sdk";

/**
 * Claude AI Planner - Single-turn Task Schedule Generator.
 *
 * Khác với agent.ts cũ (multi-turn, 15-25 turns), planner này:
 * - Chạy ĐÚNG 1 lượt suy luận duy nhất
 * - CHỈ đọc context (Read, Glob, Grep), KHÔNG chạy Bash
 * - Output: JSON JobSchedulePlan (mảng Tasks cho Laravel Horizon)
 * - Tiết kiệm ~90% token so với multi-turn
 */

/** Tools agent được phép dùng - CHỈ đọc, KHÔNG Bash */
const ALLOWED_TOOLS = ["Read", "Glob", "Grep"];

interface PlanInput {
  sessionId: string;
  projectId: string;
  message: string;
  model?: string;
  effort?: string;
  repoRoot: string;
}

interface TaskItem {
  id: string;
  type: "generate-image" | "render-scene" | "render-scene-draft" | "assemble-draft" | "assemble-video" | "run-qc";
  params: Record<string, unknown>;
  dependsOn?: string[];
  priority: number;
}

interface PlanResult {
  text: string;
  tasks: TaskItem[];
  usage?: {
    inputTokens: number;
    outputTokens: number;
    costUsd: number;
  };
}

/**
 * System prompt cho Claude: xuất JSON thuần, không chạy lệnh.
 */
function buildSystemPrompt(repoRoot: string): string {
  // Đọc AGENT_INSTRUCTIONS.md nếu có
  let instructions = "";
  try {
    const instructionsFile = fs.existsSync(path.join(repoRoot, "AGENT_INSTRUCTIONS.md"))
      ? path.join(repoRoot, "AGENT_INSTRUCTIONS.md")
      : path.join(repoRoot, "CLAUDE.md");
    instructions = fs.readFileSync(instructionsFile, "utf8");
  } catch {
    // Không có file hướng dẫn
  }

  return `${instructions}

## QUAN TRỌNG: Vai trò của bạn trong kiến trúc mới

Bạn là **AI Planner** trong hệ thống hybrid. Nhiệm vụ CỦA BẠN:
1. Đọc context của project (meta.json, assets, transcript...)
2. Phân tích yêu cầu của người dùng
3. Xuất ra MỘT JSON duy nhất chứa danh sách Tasks cần thực hiện

Bạn KHÔNG ĐƯỢC:
- Chạy lệnh Bash
- Trực tiếp render video
- Đợi kết quả render
- Tạo/sửa file

Bạn CHỈ output JSON theo format:
\`\`\`json
{
  "text": "Mô tả kế hoạch bằng lời",
  "tasks": [
    {
      "id": "task_1",
      "type": "generate-image|render-scene|render-scene-draft|assemble-draft|assemble-video|run-qc",
      "params": { "sceneId": "...", "prompt": "...", ... },
      "dependsOn": [],
      "priority": 1
    }
  ]
}
\`\`\`

Task types:
- generate-image: Sinh ảnh AI (params: prompt, sceneId)
- render-scene-draft: Render scene chất lượng draft (params: sceneId)
- render-scene: Render scene chất lượng standard (params: sceneId)
- assemble-draft: Lắp ráp video draft (params: compositionId)
- assemble-video: Lắp ráp video final (params: compositionId)
- run-qc: Kiểm tra chất lượng (params: videoPath)
`;
}

export async function planAgent(input: PlanInput): Promise<PlanResult> {
  const { sessionId, projectId, message, model, effort, repoRoot } = input;

  const systemPrompt = buildSystemPrompt(repoRoot);
  const prompt = `${systemPrompt}\n\n---\n\nProject ID: ${projectId || "không có"}\n\nYêu cầu người dùng:\n${message}`;

  const options: Record<string, unknown> = {
    cwd: repoRoot,
    permissionMode: "acceptEdits",
    allowedTools: ALLOWED_TOOLS,
    maxTurns: 1, // ĐÚNG 1 TURN duy nhất
    systemPrompt: { type: "preset", preset: "claude_code" },
    promptCaching: true,
    headers: {
      "anthropic-beta": "prompt-caching-2024-07-31",
    },
  };

  if (model) options.model = model;
  if (effort) options.effort = effort;

  let resultText = "";
  let usage = { inputTokens: 0, outputTokens: 0, costUsd: 0 };

  const q = query({ prompt, options: options as Parameters<typeof query>[0]["options"] });

  for await (const raw of q) {
    const msg = raw as Record<string, unknown>;

    if (msg.type === "result") {
      resultText = typeof msg.result === "string" ? msg.result : "";
      // Extract usage
      const u = msg.usage as Record<string, number> | undefined;
      if (u) {
        usage.inputTokens = u.input_tokens ?? 0;
        usage.outputTokens = u.output_tokens ?? 0;
      }
      usage.costUsd = typeof msg.total_cost_usd === "number" ? msg.total_cost_usd : 0;
    }
  }

  // Parse JSON từ output của Claude
  const tasks = extractTasks(resultText);

  return { text: resultText, tasks, usage };
}

/** Trích xuất mảng tasks từ output text của Claude */
function extractTasks(text: string): TaskItem[] {
  try {
    // Tìm JSON block trong text
    const jsonMatch = /\{[\s\S]*"tasks"\s*:\s*\[[\s\S]*\][\s\S]*\}/.exec(text);
    if (!jsonMatch) return [];

    const parsed = JSON.parse(jsonMatch[0]) as { tasks?: TaskItem[] };
    if (!Array.isArray(parsed.tasks)) return [];

    return parsed.tasks.filter(
      (t): t is TaskItem =>
        typeof t === "object" && t !== null && typeof t.type === "string",
    );
  } catch {
    return [];
  }
}
