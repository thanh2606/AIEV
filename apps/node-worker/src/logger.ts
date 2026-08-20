import fs from "node:fs";
import path from "node:path";
import { repoRoot } from "./config.js";

const logDir = path.join(repoRoot, "logs");
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir, { recursive: true });
}

const logFile = path.join(logDir, "node-worker.log");

function formatTimestamp(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}.${String(d.getMilliseconds()).padStart(3, "0")}`;
}

function writeToFile(level: string, message: string, meta?: any) {
  try {
    const timestamp = formatTimestamp();
    let line = `[${timestamp}] [${level}] ${message}`;
    if (meta !== undefined) {
      if (meta instanceof Error) {
        line += `\n${meta.stack || meta.message}`;
      } else if (typeof meta === "object") {
        line += ` ${JSON.stringify(meta)}`;
      } else {
        line += ` ${meta}`;
      }
    }
    line += "\n";
    fs.appendFileSync(logFile, line, "utf-8");
  } catch (err) {
    console.error("[Logger Error] Failed to write log to file:", err);
  }
}

export const logger = {
  info(message: string, meta?: any) {
    console.log(`[INFO] ${message}`, meta !== undefined ? meta : "");
    writeToFile("INFO", message, meta);
  },
  warn(message: string, meta?: any) {
    console.warn(`[WARN] ${message}`, meta !== undefined ? meta : "");
    writeToFile("WARN", message, meta);
  },
  error(message: string, meta?: any) {
    console.error(`[ERROR] ${message}`, meta !== undefined ? meta : "");
    writeToFile("ERROR", message, meta);
  },
  debug(message: string, meta?: any) {
    console.log(`[DEBUG] ${message}`, meta !== undefined ? meta : "");
    writeToFile("DEBUG", message, meta);
  },
  getLogPath(): string {
    return logFile;
  }
};
