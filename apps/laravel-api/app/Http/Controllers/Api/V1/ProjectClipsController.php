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
    public function suggest(Request $request, string $id): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        
        $projectDir = config('aiev.paths.video_projects') . "/{$id}";
        if (!file_exists("{$projectDir}/meta.json")) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Project không tồn tại']], 404);
        }
        $meta = json_decode(file_get_contents("{$projectDir}/meta.json"), true);
        
        $payload = [
            'projectId' => $id,
            'projectName' => $meta['name'] ?? '',
            'sourceDescription' => $meta['brief']['sourceDescription'] ?? '',
            'durationSec' => $meta['source']['durationSec'] ?? 0,
            'timedText' => [],
            'count' => $request->input('count', 3),
            'minSec' => $request->input('minSec', 15),
            'maxSec' => $request->input('maxSec', 60),
            'model' => $request->input('model')
        ];

        if (file_exists("{$projectDir}/transcript.json")) {
            $transcript = json_decode(file_get_contents("{$projectDir}/transcript.json"), true);
            $payload['timedText'] = array_map(fn($s) => "[{$s['start']}s - {$s['end']}s] {$s['text']}", $transcript['segments'] ?? []);
        } elseif (file_exists("{$projectDir}/script.json")) {
            $script = json_decode(file_get_contents("{$projectDir}/script.json"), true);
            $payload['timedText'] = array_column($script, 'text');
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(300)->post("{$nodeWorker}/internal/agent/clips-suggest", $payload);
            if ($res->successful()) {
                $newMeta = $res->json();
                file_put_contents("{$projectDir}/clips.json", json_encode($newMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return response()->json($newMeta);
            }
            return response()->json([
                'error' => [
                    'code' => 'AI_ERROR',
                    'message' => $res->json('error.message') ?? 'Lỗi kết nối AI (HTTP ' . $res->status() . ')'
                ]
            ], 502);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'HTTP_ERROR',
                    'message' => 'Không thể gọi AI Node Worker: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}
