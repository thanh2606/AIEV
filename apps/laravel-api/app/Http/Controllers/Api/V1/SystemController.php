<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

/**
 * System utilities: LAN info, Reveal in File Explorer, Cloudflare Tunnel.
 * Port từ index.ts, reveal.ts, tunnel.ts
 */
class SystemController extends Controller
{
    public function lanInfo(): JsonResponse
    {
        return response()->json([
            'ips' => ['127.0.0.1'],
            'webPort' => 6868,
            'tunnelDomain' => null,
        ]);
    }

    public function tunnel(): JsonResponse
    {
        return response()->json([
            'active' => false,
            'domain' => null,
        ]);
    }

    /** POST /api/v1/reveal */
    public function reveal(Request $request): JsonResponse
    {
        $relPath = $request->input('relPath');
        if (empty($relPath) || !is_string($relPath)) {
            return response()->json(['error' => ['code' => 'INVALID_RELPATH', 'message' => 'Thiếu relPath']], 400);
        }

        $repoRoot = config('aiev.repo_root');
        $abs = realpath("{$repoRoot}/{$relPath}");
        if (!$abs || !str_starts_with($abs, $repoRoot)) {
            return response()->json(['error' => ['code' => 'INVALID_RELPATH', 'message' => 'Lỗi đường dẫn ngoài repo']], 400);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen("start explorer /select,\"{$abs}\"", "r"));
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            pclose(popen("open -R \"{$abs}\"", "r"));
        } else {
            $dir = is_dir($abs) ? $abs : dirname($abs);
            pclose(popen("xdg-open \"{$dir}\" > /dev/null 2>&1 &", "r"));
        }

        return response()->json(null, 204);
    }
}
