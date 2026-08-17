<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cắt khoảng lặng + mỡ thừa của một video project.
 * Port từ apps/server/src/routes/autoTrim.ts
 */
class ProjectAutoTrimController extends Controller
{
    public function store(Request $request, string $id): JsonResponse
    {
        $level = $request->input('level', 'default');

        $job = AievJob::create([
            'project_id' => $id,
            'type' => 'auto-trim',
            'scene_id' => $level,
            'status' => 'queued',
            'progress' => 0,
            'step' => 'Khoảng lặng...',
        ]);

        return response()->json($job, 201);
    }
}
