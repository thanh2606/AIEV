<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRenderJob;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Quản lý phiên Text-to-video - đọc/ghi meta.json trong thư mục text-to-video/{id}.
 * Port từ apps/server/src/routes/textToVideo.ts
 */
class TextToVideoController extends Controller
{
    private function baseDir(): string
    {
        return config('aiev.paths.text_to_video');
    }

    /** GET /api/v1/text-to-video */
    public function index(): JsonResponse
    {
        $dir = $this->baseDir();
        if (!is_dir($dir)) return response()->json([]);

        $items = [];
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $metaFile = "{$dir}/{$name}/meta.json";
            if (!file_exists($metaFile)) continue;
            try {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) $items[] = $meta;
            } catch (\Throwable $e) {
                Log::warning("TextToVideoController read meta error for {$name}: {$e->getMessage()}", ['exception' => $e]);
                continue;
            }
        }

        usort($items, fn($a, $b) => ($b['updatedAt'] ?? '') <=> ($a['updatedAt'] ?? ''));

        return response()->json($items);
    }

    /** POST /api/v1/text-to-video */
    public function store(Request $request): JsonResponse
    {
        $body = $request->all();
        $src = $body['source'] ?? [];

        $name = trim($body['name'] ?? '');
        $autoNamed = false;
        if (empty($name)) {
            $name = $this->provisionalName($src);
            $autoNamed = true;
        }

        $id = $this->uniqueId($name);
        $now = now()->toISOString();

        $meta = [
            'id' => $id,
            'name' => $name,
            'autoNamed' => $autoNamed,
            'status' => 'draft',
            'source' => [
                'kind' => in_array($src['kind'] ?? '', ['text', 'url']) ? $src['kind'] : 'text',
                'url' => trim($src['url'] ?? ''),
                'text' => $src['text'] ?? '',
            ],
            'voice' => [
                'engine' => 'gemini',
                'model' => null,
                'name' => 'vi-VN-Standard-A',
                'style' => null,
                'language' => 'vi-VN',
                'speed' => 1.0,
            ],
            'scriptModel' => null,
            'script' => [],
            'brief' => [
                'sourceDescription' => '',
                'autoCut' => true,
                'autoCutLevel' => 'default',
                'subtitles' => true,
                'highlightEnabled' => true,
                'highlightKeywords' => [],
                'keyLayoutEnabled' => true,
                'mainKey' => '',
                'relatedKeys' => [],
                'skill' => null,
                'sfxMode' => 'recommended',
                'musicMode' => 'auto',
                'notes' => '',
                'autoIllustrations' => false,
                'illustrationModel' => null,
                'illustrationText' => false,
                'illustrationPosition' => 'auto',
                'illustrationsPerMinute' => null,
                'styleId' => null,
                'videoStyleId' => null,
            ],
            'output' => [
                'width' => 1080,
                'height' => 1920,
                'fps' => 30,
                'styleId' => null,
            ],
            'projectId' => null,
            'voiceFile' => null,
            'voiceDurationSec' => null,
            'error' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $this->writeMeta($id, $meta);
        return response()->json($meta, 201);
    }

    /** GET /api/v1/text-to-video/{id} */
    public function show(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên \"{$id}\""],
            ], 404);
        }
        return response()->json($meta);
    }

    /** PATCH /api/v1/text-to-video/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên \"{$id}\""],
            ], 404);
        }

        $body = $request->all();
        if (isset($body['name']) && trim($body['name'])) {
            $meta['name'] = trim($body['name']);
            $meta['autoNamed'] = false;
        }

        if (isset($body['source']) && is_array($body['source'])) {
            $s = $body['source'];
            $meta['source'] = array_merge($meta['source'] ?? [], [
                'kind' => in_array($s['kind'] ?? '', ['text', 'url']) ? $s['kind'] : ($meta['source']['kind'] ?? 'text'),
                'url' => isset($s['url']) ? trim($s['url']) : ($meta['source']['url'] ?? ''),
                'text' => $s['text'] ?? ($meta['source']['text'] ?? ''),
            ]);
        }

        if (isset($body['voice']) && is_array($body['voice'])) {
            $v = $body['voice'];
            $meta['voice'] = array_merge($meta['voice'] ?? [], $v);
        }

        if (isset($body['output']) && is_array($body['output'])) {
            $meta['output'] = array_merge($meta['output'] ?? [], $body['output']);
        }

        if (array_key_exists('scriptModel', $body)) {
            $meta['scriptModel'] = $body['scriptModel'];
        }

        if (isset($body['brief']) && is_array($body['brief'])) {
            $meta['brief'] = array_merge($meta['brief'] ?? [], $body['brief']);
        }

        if (isset($body['script']) && is_array($body['script'])) {
            $meta['script'] = array_map(function ($c) {
                return ['text' => is_string($c['text'] ?? null) ? $c['text'] : '', 'durationSec' => null];
            }, $body['script']);
            $meta['voiceFile'] = null;
            $meta['voiceDurationSec'] = null;
        }

        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json($meta);
    }

    /** DELETE /api/v1/text-to-video/{id} */
    public function destroy(string $id): JsonResponse
    {
        $dir = $this->baseDir() . '/' . $id;
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
        return response()->json(null, 204);
    }

    /** POST /api/v1/text-to-video/{id}/extract */
    public function extract(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Phiên \"{$id}\" không tồn tại"]], 404);
        }

        $nodeWorker = $this->getNodeWorkerUrl();
        try {
            $res = Http::post("{$nodeWorker}/api/text-to-video/{$id}/extract");
            if ($res->successful()) {
                return response()->json($res->json());
            }
        } catch (\Throwable $e) {
            Log::warning("TextToVideoController extract error for {$id}: {$e->getMessage()}", ['exception' => $e]);
        }

        // Fallback stub response if worker is offline
        $meta['status'] = 'draft';
        $this->writeMeta($id, $meta);
        return response()->json($meta);
    }

    /** POST /api/v1/text-to-video/{id}/script */
    public function script(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Phiên \"{$id}\" không tồn tại"]], 404);
        }

        $nodeWorker = $this->getNodeWorkerUrl();
        try {
            $payload = array_merge($request->all(), ['id' => $id]);
            $res = Http::timeout(300)->post("{$nodeWorker}/internal/agent/script", $payload);
            if ($res->successful()) {
                $newMeta = $res->json();
                $this->writeMeta($id, $newMeta);
                return response()->json($newMeta);
            }
            Log::error("TextToVideoController script failed (HTTP {$res->status()}): " . $res->body());
            $err = $res->json('error');
            $msg = is_string($err) ? $err : ($res->json('error.message') ?? $res->json('message') ?? 'Lỗi kết nối AI (HTTP ' . $res->status() . ')');
            return response()->json([
                'error' => [
                    'code' => 'AI_ERROR',
                    'message' => $msg
                ]
            ], 502);
        } catch (\Throwable $e) {
            Log::error('Node Worker error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'error' => [
                    'code' => 'HTTP_ERROR',
                    'message' => 'Không thể gọi AI Node Worker: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /** POST /api/v1/text-to-video/{id}/build */
    public function build(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Phiên \"{$id}\" không tồn tại"]], 404);
        }

        $meta['status'] = 'building';
        unset($meta['error']);
        $this->writeMeta($id, $meta);

        $job = AievJob::create([
            'project_id' => $id,
            'type' => 'text-to-video',
            'scene_id' => null,
            'status' => 'queued',
            'progress' => 0,
            'step' => 'Dựng video...',
        ]);

        ProcessRenderJob::dispatch($job->id);

        return response()->json(['jobId' => $job->id], 202);
    }

    private function readMeta(string $id): ?array
    {
        $file = $this->baseDir() . "/{$id}/meta.json";
        if (!file_exists($file)) return null;
        return json_decode(file_get_contents($file), true);
    }

    private function writeMeta(string $id, array $meta): void
    {
        $dir = $this->baseDir() . "/{$id}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents("{$dir}/meta.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function provisionalName(array $src): string
    {
        $url = trim($src['url'] ?? '');
        if (!empty($url)) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $slug = basename($path);
            if (!empty($slug)) return Str::slug(pathinfo($slug, PATHINFO_FILENAME));
        }
        $text = trim($src['text'] ?? '');
        if (!empty($text)) {
            return Str::slug(Str::words($text, 6, ''));
        }
        return 'text-to-video';
    }

    private function uniqueId(string $name): string
    {
        $base = Str::slug($name) ?: 'text-to-video';
        $id = $base;
        $n = 2;
        while (is_dir($this->baseDir() . '/' . $id)) {
            $id = "{$base}-{$n}";
            $n++;
        }
        return $id;
    }

    private function getNodeWorkerUrl(): string
    {
        $url = config('aiev.node_worker_url', 'http://localhost:6870');
        $hasNodeWorkerHost = (gethostbyname('node-worker') !== 'node-worker');

        if (str_contains($url, 'node-worker') && !$hasNodeWorkerHost) {
            return str_replace('node-worker', 'host.docker.internal', $url);
        }
        if (str_contains($url, 'localhost') && (gethostbyname('host.docker.internal') !== 'host.docker.internal')) {
            return str_replace('localhost', 'host.docker.internal', $url);
        }
        return $url;
    }
}
