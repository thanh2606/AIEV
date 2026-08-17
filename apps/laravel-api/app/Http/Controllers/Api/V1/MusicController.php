<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Thư viện nhạc nền (Music).
 * Port từ apps/server/src/routes/music.ts
 */
class MusicController extends Controller
{
    public function index(): JsonResponse
    {
        $dir = config('aiev.repo_root') . '/assets/music';
        if (!is_dir($dir)) return response()->json([]);

        $items = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..' || is_dir("{$dir}/{$f}")) continue;
            $items[] = [
                'file' => "music/{$f}",
                'tags' => ['background'],
                'durationMs' => 60000,
                'description' => $f,
            ];
        }
        return response()->json($items);
    }
}
