<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Xuất phụ đề .srt/.vtt + gói metadata đăng bài.
 * Port từ apps/server/src/routes/publish.ts
 */
class ProjectPublishController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $file = config('aiev.paths.video_projects') . "/{$id}/publish.json";
        if (!file_exists($file)) {
            return response()->json([
                'title' => '',
                'description' => '',
                'tags' => [],
                'subtitles' => null,
            ]);
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        return response()->json($data);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        
        // Cần truyền đủ payload cho /internal/agent/publish
        // Trong kiến trúc cũ, server tự load project meta. Trong kiến trúc mới, ta pass id để Node Worker tự load (nếu cần), hoặc ta phải load rồi pass.
        // Node Worker publish payload: { projectId, projectName, sourceDescription, durationSec, style, timedText, platforms, model }
        // Để đơn giản, ta chỉ truyền projectId và platforms, Node Worker cần tự fetch thông tin project nếu repoRoot được truyền.
        // Xem lại server.ts: publishAgent({ repoRoot, projectId, projectName, sourceDescription, durationSec, style, timedText, platforms, model });
        
        // Load project meta
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
            'style' => $meta['brief']['styleId'] ?? '', // ID style
            'timedText' => [], // Chỗ này cần transcript hoặc script. Hiện Node Worker xử lý `publishAgent` cần timedText. Ta pass mảng rỗng để node worker fallback (nếu publishAgent tự đọc file, hoặc ta phải truyền).
            'platforms' => $request->input('platforms', ['youtube', 'tiktok', 'facebook']),
            'model' => $request->input('model')
        ];

        // Đọc transcript hoặc script để làm timedText
        if (file_exists("{$projectDir}/transcript.json")) {
            $transcript = json_decode(file_get_contents("{$projectDir}/transcript.json"), true);
            $payload['timedText'] = array_map(fn($s) => "[{$s['start']}s - {$s['end']}s] {$s['text']}", $transcript['segments'] ?? []);
        } elseif (file_exists("{$projectDir}/script.json")) {
            $script = json_decode(file_get_contents("{$projectDir}/script.json"), true);
            $payload['timedText'] = array_column($script, 'text');
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(300)->post("{$nodeWorker}/internal/agent/publish", $payload);
            if ($res->successful()) {
                $newMeta = $res->json();
                file_put_contents("{$projectDir}/publish.json", json_encode($newMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return response()->json(['pack' => $newMeta]);
            }
            $err = $res->json('error');
            $msg = is_string($err) ? $err : ($res->json('error.message') ?? $res->json('message') ?? 'Lỗi kết nối AI (HTTP ' . $res->status() . ')');
            return response()->json([
                'error' => [
                    'code' => 'AI_ERROR',
                    'message' => $msg
                ]
            ], 502);
        } catch (\Throwable $e) {
            Log::error("ProjectPublishController store error for {$id}: {$e->getMessage()}", ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'HTTP_ERROR',
                    'message' => 'Không thể gọi AI Node Worker: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}
