<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Cắt short & tái chế tỉ lệ khung hình.
 * Port từ apps/server/src/routes/clips.ts
 */
class ProjectClipsController extends Controller
{
    public function index(string $id): JsonResponse
    {
        $file = config('aiev.paths.video_projects') . "/{$id}/clips.json";
        if (!file_exists($file)) {
            return response()->json(['clips' => []]);
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        return response()->json($data);
    }
}
