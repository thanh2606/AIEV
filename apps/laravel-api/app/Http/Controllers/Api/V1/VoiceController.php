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
            $res = Http::get("{$nodeWorker}/internal/tts/clone");
            if ($res->successful()) {
                return response()->json($res->json());
            }
        } catch (\Throwable) {}

        return response()->json([]);
    }

    /** POST /api/v1/voices */
    public function store(Request $request): JsonResponse
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => ['code' => 'FILE_REQUIRED', 'message' => 'Thiếu file mẫu giọng']], 400);
        }

        $file = $request->file('file');
        
        // Save file to a temporary location to pass srcAbs to node-worker
        $tempPath = sys_get_temp_dir() . '/' . uniqid('voice_') . '.' . $file->getClientOriginalExtension();
        move_uploaded_file($file->getPathname(), $tempPath);

        $nodeWorker = config('aiev.node_worker_url');
        $payload = array_merge($request->except('file'), [
            'srcAbs' => $tempPath
        ]);

        try {
            $response = Http::post("{$nodeWorker}/internal/tts/clone", $payload);
            
            // Delete temp file after Node worker is done with it
            @unlink($tempPath);

            return response()->json($response->json(), $response->status());
        } catch (\Throwable $e) {
            @unlink($tempPath);
            return response()->json(['error' => ['message' => 'Lỗi kết nối node-worker: ' . $e->getMessage()]], 500);
        }
    }

    /** PATCH /api/v1/voices/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = Http::patch("{$nodeWorker}/internal/tts/clone/{$id}", $request->all());
        return response()->json($response->json(), $response->status());
    }

    /** DELETE /api/v1/voices/{id} */
    public function destroy(string $id): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = Http::delete("{$nodeWorker}/internal/tts/clone/{$id}");
        return response()->json($response->json(), $response->status());
    }

    /** POST /api/v1/voices/{id}/preview */
    public function preview(Request $request, string $id)
    {
        $nodeWorker = config('aiev.node_worker_url');
        
        $payload = array_merge($request->all(), ['id' => $id]);
        $response = Http::post("{$nodeWorker}/internal/tts/clone-preview", $payload);
        
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['audioBase64'])) {
                $pcm = base64_decode($data['audioBase64']);
                return response($pcm, 200)
                    ->header('Content-Type', 'audio/wav')
                    ->header('x-tts-model', $data['modelUsed'] ?? 'vieneu')
                    ->header('x-tts-duration', number_format((float) ($data['durationSec'] ?? 0), 2));
            }
        }
        
        return response()->json($response->json(), $response->status());
    }
}
