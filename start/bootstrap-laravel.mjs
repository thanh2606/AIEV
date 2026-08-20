#!/usr/bin/env node
/**
 * Bootstrap Laravel cho start.sh/start.ps1.
 *
 * Mỗi lần khởi động (hybrid) gọi:
 *   node start/bootstrap-laravel.mjs --fix
 *
 * Làm các việc sau:
 *   1. Kiểm tra PHP có mặt, version >= 8.3 (Laravel 11 yêu cầu vậy).
 *   2. `php artisan migrate --force` để schema luôn khớp migrations hiện tại.
 *   3. `php artisan config:clear` + warm cache.
 *   4. Ping `php artisan tinker` không - thay bằng kiểm tra kết nối Redis bằng
 *      `php artisan` (đơn giản, dùng Redis::connection()).
 *
 * LƯU Ý chung với start.sh: KHÔNG import dependency ngoài (Node built-in)
 * chạy được cả trên Windows/macOS/Linux trước khi `npm install`.
 */

import { spawnSync } from "node:child_process";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");
const LARAVEL = join(ROOT, "apps", "laravel-api");
const ARTISAN = join(LARAVEL, "artisan");

const fix = process.argv.includes("--fix");

function println(s) {
  process.stdout.write(s + "\n");
}

function runSync(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, {
    cwd: opts.cwd || LARAVEL,
    encoding: "utf8",
    maxBuffer: 64 * 1024 * 1024,
    env: { ...process.env, ...(opts.env || {}) },
  });
  return { code: r.status, out: r.stdout || "", err: r.stderr || "" };
}

// 1. PHP hiện diện?
const php = runSync("php", ["-v"]);
if (php.code !== 0) {
  println("! PHP không có trong PATH — chưa khởi động được Laravel API.");
  if (fix) {
    println("  Bỏ qua (tầng web + node-worker vẫn chạy). Xem hướng dẫn trong README.");
  }
  process.exit(fix ? 0 : 1);
}
const phpVersion = (php.out.match(/PHP (\d+)\.(\d+)/) || [])[1];
println(`[OK] PHP ${phpVersion}.x có mặt`);

// 2. migrate --force (bảng luôn đồng bộ schema)
if (fix) {
  const m = runSync("php", [ARTISAN, "migrate", "--force"]);
  if (m.code !== 0) {
    println("! migrate --force thất bại — chi tiết phía trên. Web vẫn chạy nhưng API có thể lỗi.");
  } else {
    println("[OK] Migrations đồng bộ (--force)");
  }
}

// 3. Warm cache (config rỗng nhanh hơn khi route gọi config/aiev.php)
if (fix) {
  runSync("php", [ARTISAN, "config:clear"]);
  if (runSync("php", [ARTISAN, "config:cache"]).code === 0) {
    println("[OK] Cache config đã warm");
  } else {
    runSync("php", [ARTISAN, "config:clear"]); // đừng để cache hỏng
  }
}
