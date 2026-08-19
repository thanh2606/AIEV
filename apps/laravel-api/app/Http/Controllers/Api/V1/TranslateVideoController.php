<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Quản lý phiên Dịch video.
 * Port từ apps/server/src/routes/translateVideo.ts
 */
class TranslateVideoController extends Controller
{
    private function baseDir(): string
    {
        return config('aiev.paths.translate_video');
    }

    /** GET /api/v1/translate-video/fonts */
    public function fonts(): JsonResponse
    {
        return response()->json([
            ['id' => 'Inter', 'name' => 'Inter'],
            ['id' => 'Roboto', 'name' => 'Roboto'],
            ['id' => 'Montserrat', 'name' => 'Montserrat'],
        ]);
    }

    /** GET /api/v1/translate-video/stt-providers */
    public function sttProviders(): JsonResponse
    {
        return response()->json([
            ['id' => 'whisper', 'name' => 'Whisper (On-device)', 'diarization' => false, 'available' => true],
            ['id' => 'soniox', 'name' => 'Soniox AI', 'diarization' => true, 'available' => !empty(config('aiev.soniox_api_key'))],
        ]);
    }

    /** GET /api/v1/translate-video */
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
                Log::warning("TranslateVideoController read meta error for {$name}: {$e->getMessage()}", ['exception' => $e]);
                continue;
            }
        }

        usort($items, fn ($a, $b) => ($b['updatedAt'] ?? '') <=> ($a['updatedAt'] ?? ''));

        return response()->json($items);
    }

    /** POST /api/v1/translate-video */
    public function store(Request $request): JsonResponse
    {
        $body = $request->all();
        $name = trim($body['name'] ?? '');
        $autoNamed = false;
        if (empty($name)) {
            $name = 'Video dịch';
            $autoNamed = true;
        }

        $id = Str::slug($name) ?: 'translate-video-' . time();
        $now = now()->toISOString();

        $meta = [
            'id' => $id,
            'name' => $name,
            'autoNamed' => $autoNamed,
            'status' => 'draft',
            'source' => [
                'relPath' => '',
                'durationSec' => 0,
                'width' => 1920,
                'height' => 1080,
                'fps' => 30,
            ],
            'sourceLang' => $body['sourceLang'] ?? 'auto',
            'targetLang' => $body['targetLang'] ?? 'vi',
            'dubLang' => $body['dubLang'] ?? null,
            'mode' => $body['mode'] ?? 'both',
            'sttProvider' => $body['sttProvider'] ?? 'whisper',
            'subtitleStyle' => [
                'fontFamily' => 'Inter',
                'fontSizePx' => 48,
                'bottomPx' => 120,
            ],
            'dub' => [
                'engine' => 'gemini',
                'voices' => [],
            ],
            'cues' => [],
            'transcriptFile' => null,
            'outputFile' => null,
            'error' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $this->writeMeta($id, $meta);
        return response()->json($meta, 201);
    }

    /** GET /api/v1/translate-video/{id} */
    public function show(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên dịch \"{$id}\""],
            ], 404);
        }
        return response()->json($meta);
    }

    /** PATCH /api/v1/translate-video/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên dịch \"{$id}\""],
            ], 404);
        }

        $body = $request->all();
        if (isset($body['name'])) $meta['name'] = trim($body['name']);
        if (isset($body['cues']) && is_array($body['cues'])) $meta['cues'] = $body['cues'];
        if (isset($body['targetLang'])) $meta['targetLang'] = $body['targetLang'];

        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json($meta);
    }

    /** DELETE /api/v1/translate-video/{id} */
    public function destroy(string $id): JsonResponse
    {
        $dir = $this->baseDir() . '/' . $id;
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
        return response()->json(null, 204);
    }

    /** POST /api/v1/translate-video/{id}/source */
    public function source(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên dịch \"{$id}\""]], 404);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $dir = $this->baseDir() . "/{$id}";
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = $file->getClientOriginalExtension();
            $dest = "{$dir}/source.{$ext}";
            $file->move($dir, "source.{$ext}");

            $meta['source']['relPath'] = str_replace(config('aiev.repo_root') . '/', '', $dest);
            $meta['updatedAt'] = now()->toISOString();
            $this->writeMeta($id, $meta);
        }

        return response()->json($meta);
    }

    /** POST /api/v1/translate-video/{id}/transcribe */
    public function transcribe(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên dịch \"{$id}\""]], 404);
        }

        $job = AievJob::create([
            'project_id' => $id,
            'type' => 'translate-video',
            'scene_id' => 'transcribe',
            'status' => 'queued',
            'progress' => 0,
            'step' => 'Bóc lời thoại...',
        ]);

        return response()->json(['jobId' => $job->id], 202);
    }

    /** POST /api/v1/translate-video/{id}/translate */
    public function translate(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên dịch \"{$id}\""]], 404);
        }

        $meta['status'] = 'translated';
        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json($meta);
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
}
