<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Quản lý Thumbnails cho Project & Scene.
 * Port từ apps/server/src/routes/thumbnails.ts
 */
class ThumbnailController extends Controller
{
    public function generateProjectThumbnail(string $id): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'thumbnail' => "video-projects/{$id}/thumbnail.png",
        ]);
    }

    public function generateSceneThumbnail(string $id, string $sceneId): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'thumbnail' => "video-projects/{$id}/renders/{$sceneId}.png",
        ]);
    }
}
