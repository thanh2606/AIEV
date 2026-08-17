<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * QC tự động trên bản draft (kiểm tra audio/video sync, black frames, resolution).
 * Port từ apps/server/src/routes/qc.ts
 */
class ProjectQcController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $file = config('aiev.paths.video_projects') . "/{$id}/qc.json";
        if (!file_exists($file)) {
            return response()->json([
                'status' => 'pending',
                'passed' => true,
                'issues' => [],
            ]);
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        return response()->json($data);
    }
}
