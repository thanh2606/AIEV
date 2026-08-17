<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cấu hình render & tăng tốc phần cứng.
 * Port từ apps/server/src/routes/renderSettingsRoute.ts
 */
class RenderSettingsController extends Controller
{
    /** GET /api/v1/render-settings */
    public function show(): JsonResponse
    {
        $defaults = [
            'workers' => 2,
            'browserGpu' => true,
            'gpuEncodeDraft' => true,
            'gpuEncodeFinal' => true,
            'fastCapture' => true,
            'remotionConcurrency' => 2,
            'queueConcurrency' => 2,
            'draftFps' => 15,
            'qcGate' => true,
            'updateChannel' => 'stable',
            'aiMaxAttempts' => 12,
            'aiMaxTurns' => 30,
        ];

        return response()->json([
            'settings' => $defaults,
            'defaults' => $defaults,
            'hardware' => [
                'cpuCores' => 8,
                'totalRamGb' => 16,
                'gpuName' => 'NVIDIA GPU',
            ],
            'recommended' => [
                'workers' => 2,
                'concurrency' => 2,
                'maxWorkers' => 4,
            ],
        ]);
    }

    /** PUT /api/v1/render-settings */
    public function update(Request $request): JsonResponse
    {
        $settings = $request->all();
        return response()->json(['settings' => $settings]);
    }
}
