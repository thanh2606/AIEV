<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ghi chú duyệt draft theo mốc thời gian.
 * Port từ apps/server/src/routes/review.ts
 */
class ProjectReviewController extends Controller
{
    private function reviewFile(string $id): string
    {
        return config('aiev.paths.video_projects') . "/{$id}/review.json";
    }

    /** GET /api/projects/{id}/review */
    public function show(string $id): JsonResponse
    {
        $file = $this->reviewFile($id);
        if (!file_exists($file)) {
            return response()->json([
                'notes' => [],
                'updatedAt' => null,
            ]);
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        return response()->json($data);
    }

    /** POST /api/projects/{id}/review */
    public function store(Request $request, string $id): JsonResponse
    {
        $file = $this->reviewFile($id);
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $notes = $request->input('notes', []);
        $data = [
            'notes' => $notes,
            'updatedAt' => now()->toISOString(),
        ];

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return response()->json($data);
    }
}
