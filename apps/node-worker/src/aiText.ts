import { query } from "@anthropic-ai/claude-agent-sdk";

export interface AiTextResult {
  text: string;
  inputTokens: number;
  outputTokens: number;
  costUsd: number;
}

export async function generateText(input: {
  prompt: string;
  repoRoot: string;
  timeoutMs?: number;
  model?: string | null;
}): Promise<AiTextResult> {
  const timeoutMs = input.timeoutMs ?? 3 * 60_000;
  const options: Record<string, unknown> = {
    cwd: input.repoRoot,
    maxTurns: 1,
    allowedTools: [],
    settingSources: [],
    permissionMode: "default",
    promptCaching: true,
    headers: {
      "anthropic-beta": "prompt-caching-2024-07-31",
    },
  };
  const selectedModel = input.model || process.env.ANTHROPIC_MODEL || "claude-3-5-sonnet-20241022";
  options.model = selectedModel;

  let text = "";
  let inputTokens = 0;
  let outputTokens = 0;
  let costUsd = 0;

  try {
    const q = query({
      prompt: input.prompt,
      options: options as Parameters<typeof query>[0]["options"],
    });

    const run = (async () => {
      for await (const raw of q) {
        const msg = raw as {
          type?: string;
          result?: string;
          total_cost_usd?: number;
          usage?: {
            input_tokens?: number;
            output_tokens?: number;
            cache_read_input_tokens?: number;
            cache_creation_input_tokens?: number;
          };
        };
        if (msg.type === "result") {
          inputTokens =
            (msg.usage?.input_tokens ?? 0) +
            (msg.usage?.cache_creation_input_tokens ?? 0) +
            (msg.usage?.cache_read_input_tokens ?? 0);
          outputTokens = msg.usage?.output_tokens ?? 0;
          costUsd = typeof msg.total_cost_usd === "number" ? msg.total_cost_usd : 0;
          if (typeof msg.result === "string") text = msg.result;
        }
      }
    })();
    
    run.catch(() => {});

    let timer: NodeJS.Timeout | undefined;
    const timeout = new Promise<never>((_, reject) => {
      timer = setTimeout(() => {
        void q.interrupt().catch(() => {});
        reject(new Error(`Quá ${Math.round(timeoutMs / 60_000)} phút chưa có kết quả`));
      }, timeoutMs);
    });
    
    try {
      await Promise.race([run, timeout]);
    } finally {
      clearTimeout(timer);
    }
  } catch (err) {
    throw new Error(`Gọi AI thất bại: ${err instanceof Error ? err.message : String(err)}`);
  }

  return { text, inputTokens, outputTokens, costUsd };
}

export function extractJson<T = unknown>(text: string): T | null {
  const tryParse = (s: string): T | null => {
    try {
      return JSON.parse(s) as T;
    } catch {
      return null;
    }
  };

  const fenceRe = /```(?:json)?[^\n]*\r?\n([\s\S]*?)```/g;
  let m: RegExpExecArray | null;
  while ((m = fenceRe.exec(text)) !== null) {
    const parsed = tryParse(m[1].trim());
    if (parsed !== null) return parsed;
  }

  const trimmed = text.trim();
  const direct = tryParse(trimmed);
  if (direct !== null) return direct;

  for (const [open, close] of [
    ["{", "}"],
    ["[", "]"],
  ] as const) {
    const i = trimmed.indexOf(open);
    const j = trimmed.lastIndexOf(close);
    if (i >= 0 && j > i) {
      const parsed = tryParse(trimmed.slice(i, j + 1));
      if (parsed !== null) return parsed;
    }
  }
  return null;
}
