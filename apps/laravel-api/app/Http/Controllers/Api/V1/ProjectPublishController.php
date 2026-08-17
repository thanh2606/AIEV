<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Xuất phụ đề .srt/.vtt + gói metadata đăng bài.
 * Port từ apps/server/src/routes/publish.ts
 */
class ProjectPublishController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $file = config('aiev.paths.video_projects') . "/{$id}/publish.json";
        if (!file_exists($file)) {
            return response()->json([
                'title' => '',
                'description' => '',
                'tags' => [],
                'subtitles' => null,
            ]);
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        return response()->json($data);
    }
}
