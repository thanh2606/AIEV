export function geminiApiKey(): string | null {
  return process.env.GEMINI_API_KEY || null;
}
