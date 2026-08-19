<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Quản lý video projects - đọc/ghi meta.json trên đĩa.
 * Port từ apps/server/src/routes/projects.ts
 */
class ProjectController extends Controller
{
    private function projectsDir(): string
    {
        return config('aiev.paths.video_projects');
    }

    /** GET /api/v1/projects */
    public function index(): JsonResponse
    {
        $dir = $this->projectsDir();
        if (!is_dir($dir)) return response()->json([]);

        $projects = [];
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $metaFile = "{$dir}/{$name}/meta.json";
            if (!file_exists($metaFile)) continue;
            try {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) $projects[] = $meta;
            } catch (\Throwable $e) {
                Log::warning("ProjectController read meta error for {$name}: {$e->getMessage()}", ['exception' => $e]);
                continue;
            }
        }

        // Sắp xếp mới nhất lên đầu
        usort($projects, fn ($a, $b) =>
            ($b['updatedAt'] ?? '') <=> ($a['updatedAt'] ?? '')
        );

        return response()->json($projects);
    }

    /** GET /api/v1/projects/{id} */
    public function show(string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Project \"{$id}\" không tồn tại"],
            ], 404);
        }
        return response()->json($meta);
    }

    /** PATCH /api/v1/projects/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Project \"{$id}\" không tồn tại"],
            ], 404);
        }

        $patch = $request->all();
        $meta = array_merge($meta, $patch);
        $meta['updatedAt'] = now()->toISOString();

        $this->writeMeta($id, $meta);

        return response()->json($meta);
    }

    /** DELETE /api/v1/projects/{id} */
    public function destroy(string $id): JsonResponse
    {
        $dir = $this->projectsDir() . '/' . $id;
        if (!is_dir($dir)) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Project \"{$id}\" không tồn tại"],
            ], 404);
        }

        File::deleteDirectory($dir);
        return response()->json(null, 204);
    }

    public function readMeta(string $id): ?array
    {
        $file = $this->projectsDir() . "/{$id}/meta.json";
        if (!file_exists($file)) return null;
        return json_decode(file_get_contents($file), true);
    }

    private function writeMeta(string $id, array $meta): void
    {
        $dir = $this->projectsDir() . "/{$id}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(
            "{$dir}/meta.json",
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /** POST /api/v1/projects/{id}/clone */
    public function clone(Request $request, string $id): JsonResponse
    {
        $srcMeta = $this->readMeta($id);
        if (!$srcMeta) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Project \"{$id}\" không tồn tại"],
            ], 404);
        }

        $name = trim($request->input('name', ''));
        if (empty($name)) {
            $name = $srcMeta['name'] . ' (bản sao)';
        }

        // Tạo newId: slugify tên
        $baseId = \Illuminate\Support\Str::slug($name);
        if (empty($baseId)) $baseId = "{$id}-copy";
        
        $newId = $baseId;
        $counter = 2;
        $projectsDir = $this->projectsDir();
        while (is_dir("{$projectsDir}/{$newId}")) {
            $newId = "{$baseId}-{$counter}";
            $counter++;
        }

        // Copy directory
        $srcDir = "{$projectsDir}/{$id}";
        $dstDir = "{$projectsDir}/{$newId}";
        $skip = ['renders', 'cache', 'verify', 'node_modules', 'props.resolved.json'];
        
        mkdir($dstDir, 0755, true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPathName = $iterator->getSubPathname();
            // Skip root ignored dirs/files
            $topLevel = explode('/', str_replace('\\', '/', $subPathName))[0];
            if (in_array($topLevel, $skip)) continue;

            $targetPath = $dstDir . DIRECTORY_SEPARATOR . $subPathName;
            if ($item->isDir()) {
                if (!is_dir($targetPath)) mkdir($targetPath);
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }

        // Ensure renders directory exists
        if (!is_dir("{$dstDir}/renders")) {
            mkdir("{$dstDir}/renders", 0755, true);
        }

        $newMeta = $srcMeta;
        $newMeta['id'] = $newId;
        $newMeta['name'] = $name;
        $newMeta['status'] = 'draft';
        $newMeta['output'] = null;
        unset($newMeta['outputInfo']);
        $newMeta['createdAt'] = now()->toISOString();
        $newMeta['updatedAt'] = now()->toISOString();

        $this->writeMeta($newId, $newMeta);

        return response()->json($newMeta, 201);
    }

    private function getDirSize(string $dir): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /** GET /api/v1/projects/{id}/junk */
    public function junk(string $id): JsonResponse
    {
        if (!$this->readMeta($id)) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Project không tồn tại']], 404);
        }

        $dir = $this->projectsDir() . "/{$id}";
        $items = [];
        $totalBytes = 0;

        foreach (['renders', 'verify', 'cache'] as $name) {
            $abs = "{$dir}/{$name}";
            if (is_dir($abs)) {
                $size = $this->getDirSize($abs);
                if ($size > 0) {
                    $items[] = ['relPath' => "video-projects/{$id}/{$name}/", 'size' => $size];
                    $totalBytes += $size;
                }
            }
        }

        $propsFile = "{$dir}/props.resolved.json";
        if (file_exists($propsFile)) {
            $size = filesize($propsFile);
            $items[] = ['relPath' => "video-projects/{$id}/props.resolved.json", 'size' => $size];
            $totalBytes += $size;
        }

        return response()->json(['items' => $items, 'totalBytes' => $totalBytes]);
    }

    /** POST /api/v1/projects/{id}/junk/clean */
    public function cleanJunk(string $id): JsonResponse
    {
        if (!$this->readMeta($id)) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Project không tồn tại']], 404);
        }

        $dir = $this->projectsDir() . "/{$id}";
        $freed = 0;
        $count = 0;

        foreach (['renders', 'verify', 'cache'] as $name) {
            $abs = "{$dir}/{$name}";
            if (is_dir($abs)) {
                $freed += $this->getDirSize($abs);
                File::deleteDirectory($abs);
                $count++;
            }
        }

        $propsFile = "{$dir}/props.resolved.json";
        if (file_exists($propsFile)) {
            $freed += filesize($propsFile);
            unlink($propsFile);
            $count++;
        }

        if (!is_dir("{$dir}/renders")) {
            mkdir("{$dir}/renders", 0755, true);
        }

        return response()->json(['freedBytes' => $freed, 'deleted' => $count]);
    }

    /** PUT /api/v1/projects/{id}/name */
    public function updateName(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Project không tồn tại']], 404);
        }

        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['error' => ['code' => 'INVALID_NAME', 'message' => 'Tên project không được trống']], 400);
        }

        $meta['name'] = $name;
        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json($meta);
    }

    /** PUT /api/v1/projects/{id}/tags */
    public function updateTags(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Project không tồn tại']], 404);
        }

        $tags = $request->input('tags', []);
        if (!is_array($tags)) {
            return response()->json(['error' => ['code' => 'INVALID_TAGS', 'message' => 'Tags phải là mảng']], 400);
        }

        $meta['tags'] = $tags;
        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json(['tags' => $tags]);
    }

    /** PUT /api/v1/projects/{id}/brief */
    public function updateBrief(Request $request, string $id): JsonResponse
    {
        $meta = $this->readMeta($id);
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Project không tồn tại']], 404);
        }

        $brief = $request->all();
        $meta['brief'] = array_merge($meta['brief'] ?? [], $brief);
        $meta['updatedAt'] = now()->toISOString();
        $this->writeMeta($id, $meta);

        return response()->json($meta['brief']);
    }

    /** POST /api/v1/projects/{id}/edit */
    public function edit(Request $request, string $id): JsonResponse
    {
        \Illuminate\Support\Facades\Log::info("edit method called for id: {$id}");
        $file = config('aiev.paths.video_projects') . "/{$id}/meta.json";
        $meta = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
        if (!$meta) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => "Project \"{$id}\" không tồn tại"]], 404);
        }

        $brief = array_merge([
            'sourceDescription' => '',
            'autoCut' => true,
            'autoCutLevel' => 'default',
            'subtitles' => true,
            'highlightEnabled' => true,
            'keyLayoutEnabled' => true,
            'autoIllustrations' => false,
            'sfxMode' => 'recommended',
            'musicMode' => 'auto',
        ], $meta['brief'] ?? []);

        $style = \App\Services\ProjectHelper::getStyle($brief['styleId'] ?? null);
        $brandLogoFile = \App\Services\ProjectHelper::syncBrandLogo($id, $style);

        $sfx = \App\Services\ProjectHelper::readSfxLibrary();
        $recommendedSfx = array_filter($sfx, fn($e) => in_array('hay-dung', $e['tags'] ?? []));
        
        $music = \App\Services\ProjectHelper::readMusicLibrary();
        
        $prompt = \App\Services\EditPromptBuilder::build([
            'id' => $id,
            'meta' => $meta,
            'brief' => $brief,
            'assets' => \App\Services\ProjectHelper::listProjectAssets($id),
            'recommendedSfx' => $recommendedSfx,
            'music' => $music,
            'style' => $style,
            'brandLogoFile' => $brandLogoFile,
            'extraNotes' => $request->input('extraNotes', ''),
            'brandLogoLibraryCount' => \App\Services\ProjectHelper::countBrandLogos(),
            'videoStyle' => \App\Services\ProjectHelper::getVideoStyle($brief['videoStyleId'] ?? null)
        ]);

        $sessionId = 'ses_' . \Illuminate\Support\Str::random(21);
        
        $session = \App\Models\ChatSession::create([
            'session_id' => $sessionId,
            'title' => 'Edit: ' . ($meta['name'] ?? $id),
            'project_id' => $id,
            'model' => $request->input('model'),
            'effort' => $request->input('effort'),
            'goal' => 'final',
            'status' => 'idle',
        ]);

        \App\Models\ChatMessage::create([
            'session_id' => $sessionId,
            'role' => 'user',
            'kind' => 'text',
            'content' => $prompt,
            'created_at' => now(),
        ]);

        dispatch(new \App\Jobs\ProcessChatMessage($sessionId, $prompt));

        return response()->json(['sessionId' => $sessionId], 202);
    }
}
