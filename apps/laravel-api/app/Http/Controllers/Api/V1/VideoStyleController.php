<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Phong cách dựng video (giấy gấp, mực tàu...).
 * Port từ apps/server/src/routes/videoStyles.ts
 */
class VideoStyleController extends Controller
{
    public function index(): JsonResponse
    {
        $file = config('aiev.repo_root') . '/assets/video-styles/styles.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) return response()->json($data);
        }

        return response()->json([
            ['id' => 'origami', 'name' => 'Giấy gấp (Origami)', 'palette' => 'brand', 'motion' => 'Gấp giấy stop-motion'],
            ['id' => 'ink', 'name' => 'Mực tàu (Ink Wash)', 'palette' => 'loose', 'motion' => 'Loang mực mềm mại'],
            ['id' => 'stickman', 'name' => 'Người que (Stick Figure)', 'palette' => 'brand', 'motion' => 'Vẽ nét tối giản'],
        ]);
    }
}
