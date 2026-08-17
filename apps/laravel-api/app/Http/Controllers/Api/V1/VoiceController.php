<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Thư viện giọng đọc nhân bản (VieNeu).
 * Port từ apps/server/src/routes/voices.ts
 */
class VoiceController extends Controller
{
    /** GET /api/v1/voices */
    public function index(Request $request): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        try {
            $res = Http::get("{$nodeWorker}/api/voices", $request->query());
            if ($res->successful()) {
                return response()->json($res->json());
            }
        } catch (\Throwable) {}

        return response()->json([]);
    }

    /** POST /api/v1/voices */
    public function store(Request $request): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        // Proxy multipart file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $response = Http::attach(
                'file',
                file_get_contents($file->getPathname()),
                $file->getClientOriginalName()
            )->post("{$nodeWorker}/api/voices", $request->except('file'));
            
            return response()->json($response->json(), $response->status());
        }

        return response()->json(['error' => ['code' => 'FILE_REQUIRED', 'message' => 'Thiếu file mẫu giọng']], 400);
    }

    /** PATCH /api/v1/voices/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = Http::patch("{$nodeWorker}/api/voices/{$id}", $request->all());
        return response()->json($response->json(), $response->status());
    }

    /** DELETE /api/v1/voices/{id} */
    public function destroy(string $id): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = Http::delete("{$nodeWorker}/api/voices/{$id}");
        return response()->json($response->json(), $response->status());
    }

    /** POST /api/v1/voices/{id}/preview */
    public function preview(Request $request, string $id)
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = Http::post("{$nodeWorker}/api/voices/{$id}/preview", $request->all());
        
        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type'))
                ->header('X-Tts-Model', $response->header('X-Tts-Model'))
                ->header('X-Tts-Duration', $response->header('X-Tts-Duration'));
        }
        
        return response()->json($response->json(), $response->status());
    }
}
