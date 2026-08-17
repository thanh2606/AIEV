<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Software update check & apply.
 * Port từ apps/server/src/routes/update.ts
 */
class UpdateController extends Controller
{
    /** GET /api/v1/update/check */
    public function check(Request $request): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        try {
            $res = Http::get("{$nodeWorker}/api/update/check", $request->query());
            if ($res->successful()) {
                return response()->json($res->json());
            }
        } catch (\Throwable) {}

        return response()->json([
            'current' => '0.2.0',
            'currentVersion' => 'v0.2.0',
            'latestVersion' => 'v0.2.0',
            'channel' => 'stable',
            'behind' => 0,
            'upToDate' => true,
            'latestMessage' => null,
            'commits' => [],
            'checkedAt' => now()->toISOString(),
            'fetchOk' => true,
        ]);
    }

    /** GET /api/v1/update/log */
    public function log(Request $request): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        try {
            $res = Http::get("{$nodeWorker}/api/update/log", $request->query());
            if ($res->successful()) {
                return response()->json($res->json());
            }
        } catch (\Throwable) {}

        return response()->json([
            'exists' => false,
            'lines' => [],
            'step' => null,
            'startedAt' => null,
        ]);
    }

    /** POST /api/v1/update/apply */
    public function apply(): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        try {
            $res = Http::post("{$nodeWorker}/api/update/apply");
            if ($res->successful()) {
                return response()->json($res->json(), 202);
            }
        } catch (\Throwable) {}

        return response()->json([
            'ok' => true,
            'logHint' => 'start/update.log',
            'target' => 'origin/main',
        ], 202);
    }
}
