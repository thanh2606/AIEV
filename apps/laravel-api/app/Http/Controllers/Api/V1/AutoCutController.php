<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Quản lý phiên Cắt video tự động (Auto-cut).
 * Port từ apps/server/src/routes/autoCut.ts
 */
class AutoCutController extends Controller
{
    private function baseDir(): string
    {
        return config('aiev.paths.auto_cut');
    }

    /** GET /api/v1/auto-cut/sources */
    public function sources(): JsonResponse
    {
        $importsDir = config('aiev.paths.imports');
        $files = [];

        if (is_dir($importsDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($importsDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v'])) {
                    $rel = str_replace(config('aiev.repo_root') . '/', '', $file->getPathname());
                    $files[] = [
                        'kind' => 'video',
                        'relPath' => $rel,
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'updatedAt' => date('c', $file->getMTime()),
                    ];
                }
            }
        }

        return response()->json(['files' => $files]);
    }

    /** GET /api/v1/auto-cut */
    public function index(): JsonResponse
    {
        $dir = $this->baseDir();
        if (!is_dir($dir)) return response()->json(['sessions' => []]);

        $sessions = [];
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $metaFile = "{$dir}/{$name}/meta.json";
            if (!file_exists($metaFile)) continue;
            try {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) $sessions[] = $meta;
            } catch (\Throwable) {
                continue;
            }
        }

        usort($sessions, fn ($a, $b) => ($b['updatedAt'] ?? '') <=> ($a['updatedAt'] ?? ''));

        return response()->json(['sessions' => $sessions]);
    }

    /** POST /api/v1/auto-cut */
    public function store(Request $request): JsonResponse
    {
        $body = $request->all();
        $sourceRel = trim($body['sourceRel'] ?? '');

        $name = trim($body['name'] ?? '');
        if (empty($name)) {
            $name = pathinfo($sourceRel, PATHINFO_FILENAME) ?: 'auto-cut';
        }

        $id = Str::slug($name) ?: 'auto-cut-' . time();
        $now = now()->toISOString();

        $meta = [
            'id' => $id,
            'name' => $name,
            'status' => 'draft',
            'source' => [
                'relPath' => $sourceRel,
                'width' => 1920,
                'height' => 1080,
                'fps' => 30,
                'durationSec' => 60,
                'rotation' => 0,
            ],
            'mode' => $body['mode'] ?? 'time',
            'params' => $body['params'] ?? ['minutes' => 5],
            'output' => $body['output'] ?? [
                'aspect' => '9:16',
                'layout' => 'auto',
                'background' => 'gemini',
                'styleId' => null,
                'fps' => null,
            ],
            'brief' => $body['brief'] ?? [],
            'transcribe' => $body['transcribe'] ?? true,
            'autoEdit' => $body['autoEdit'] ?? false,
            'transcriptRel' => null,
            'segments' => [],
            'error' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $this->writeMeta($id, $meta);
        return response()->json(['session' => $meta], 201);
    }

    /** GET /api/v1/auto-cut/{id} */
    public function show(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên cắt \"{$id}\""],
            ], 404);
        }
        return response()->json(['session' => $meta]);
    }

    /** PATCH /api/v1/auto-cut/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên cắt \"{$id}\""],
            ], 404);
        }

        $body = $request->all();
        if (isset($body['name'])) $meta['name'] = trim($body['name']);
        if (isset($body['autoEdit'])) $meta['autoEdit'] = (bool) $body['autoEdit'];
        if (isset($body['brief'])) $meta['brief'] = array_merge($meta['brief'] ?? [], $body['brief']);
        if (isset($body['segments'])) $meta['segments'] = $body['segments'];

        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json(['session' => $meta]);
    }

    /** DELETE /api/v1/auto-cut/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $dir = $this->baseDir() . '/' . $id;
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
        return response()->json(null, 204);
    }

    /** POST /api/v1/auto-cut/{id}/plan */
    public function plan(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên cắt \"{$id}\""]], 404);
        }

        $job = AievJob::create([
            'project_id' => $id,
            'type' => 'auto-cut',
            'scene_id' => 'plan',
            'status' => 'queued',
            'progress' => 0,
            'step' => 'Lập kế hoạch cắt...',
        ]);

        return response()->json(['job' => $job->toArray()], 202);
    }

    /** POST /api/v1/auto-cut/{id}/cut */
    public function cut(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Không tìm thấy phiên cắt \"{$id}\""]], 404);
        }

        $job = AievJob::create([
            'project_id' => $id,
            'type' => 'auto-cut',
            'scene_id' => 'cut',
            'status' => 'queued',
            'progress' => 0,
            'step' => 'Tiến hành cắt video...',
        ]);

        return response()->json(['job' => $job->toArray()], 202);
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
