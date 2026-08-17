import dotenv from "dotenv";
import path from "node:path";
import { query } from "@anthropic-ai/claude-agent-sdk";

dotenv.config({ path: path.join(process.cwd(), "../../.env") });

async function main() {
  console.log("Starting Claude test with API KEY:", process.env.ANTHROPIC_API_KEY ? "Loaded" : "Missing");
  try {
    const q = query({
      prompt: "Hello, reply with only the word OK",
      options: {
        maxTurns: 1,
        allowedTools: [],
        settingSources: [],
        permissionMode: "auto",
        promptCaching: false,
      }
    });

    for await (const msg of q) {
      console.log(msg);
    }
    console.log("Done");
  } catch (err) {
    console.error("Error:", err);
  }
}

main();
