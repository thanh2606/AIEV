<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Thư viện hiệu ứng âm thanh (SFX) & Nhạc nền (Music).
 * Port từ apps/server/src/routes/sfx.ts & music.ts
 */
class SfxController extends Controller
{
    public function index(): JsonResponse
    {
        $dir = config('aiev.repo_root') . '/assets/sfx';
        if (!is_dir($dir)) return response()->json([]);

        $items = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..' || is_dir("{$dir}/{$f}")) continue;
            $items[] = [
                'file' => "sfx/{$f}",
                'tags' => ['effect'],
                'durationMs' => 1000,
                'description' => $f,
            ];
        }
        return response()->json($items);
    }
}
