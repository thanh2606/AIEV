<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quản lý Style Design & Grade Presets.
 * Port từ apps/server/src/routes/stylesRoute.ts & color.ts
 */
class StyleController extends Controller
{
    private const GRADE_PRESETS = [
        ['id' => 'tu-nhien', 'label' => 'Tự nhiên'],
        ['id' => 'cinematic', 'label' => 'Cinematic'],
        ['id' => 'tuoi-sang', 'label' => 'Tươi sáng'],
        ['id' => 'am', 'label' => 'Ấm'],
        ['id' => 'lanh', 'label' => 'Lạnh'],
    ];

    /** GET /api/grade-presets */
    public function gradePresets(): JsonResponse
    {
        return response()->json(self::GRADE_PRESETS);
    }

    /** GET /api/styles */
    public function index(): JsonResponse
    {
        $stylesDir = config('aiev.repo_root') . '/assets/styles';
        $file = "{$stylesDir}/styles.json";

        if (!file_exists($file)) {
            return response()->json([
                'defaultId' => null,
                'styles' => [],
            ]);
        }

        $content = json_decode(file_get_contents($file), true) ?: [];
        return response()->json($content);
    }

    /** GET /api/styles/{id} */
    public function show(string $id): JsonResponse
    {
        $styles = $this->index()->getData(true);
        $found = collect($styles['styles'] ?? [])->firstWhere('id', $id);

        if (!$found) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Style \"{$id}\" không tồn tại"],
            ], 404);
        }

        return response()->json($found);
    }
}
