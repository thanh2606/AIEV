<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quản lý Assets (video, audio, image).
 * Port từ apps/server/src/routes/assets.ts
 */
class AssetController extends Controller
{
    public function projectAssets(string $id): JsonResponse
    {
        $dir = config('aiev.paths.video_projects') . "/{$id}/assets";
        if (!is_dir($dir)) {
            return response()->json([]);
        }

        $files = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $files[] = [
                'name' => $f,
                'relPath' => "video-projects/{$id}/assets/{$f}",
                'size' => filesize("{$dir}/{$f}"),
                'mtime' => date('c', filemtime("{$dir}/{$f}")),
                'kind' => $this->detectKind($f),
            ];
        }

        return response()->json($files);
    }

    public function upload(Request $request): JsonResponse
    {
        if (!$request->hasFile('file')) {
            return response()->json([
                'error' => ['code' => 'BAD_REQUEST', 'message' => 'Không có file được tải lên'],
            ], 400);
        }

        $file = $request->file('file');
        $projectId = $request->input('projectId');

        $targetDir = $projectId
            ? config('aiev.paths.video_projects') . "/{$projectId}/assets"
            : config('aiev.paths.assets');

        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

        $name = $file->getClientOriginalName();
        $file->move($targetDir, $name);

        return response()->json([
            'ok' => true,
            'name' => $name,
            'relPath' => $projectId ? "video-projects/{$projectId}/assets/{$name}" : "assets/{$name}",
        ]);
    }

    private function detectKind(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'mov', 'webm', 'mkv', 'avi'])) return 'video';
        if (in_array($ext, ['mp3', 'wav', 'aac', 'm4a', 'flac'])) return 'audio';
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'])) return 'image';
        return 'other';
    }

    private function getAssetEntry(string $projectId, string $file): array
    {
        $assetsJsonPath = config('aiev.paths.video_projects') . "/{$projectId}/assets/assets.json";
        if (!file_exists($assetsJsonPath)) return [];
        return json_decode(file_get_contents($assetsJsonPath), true) ?? [];
    }

    private function writeAssetEntry(string $projectId, string $file, array $data): void
    {
        $assetsJsonPath = config('aiev.paths.video_projects') . "/{$projectId}/assets/assets.json";
        $entries = [];
        if (file_exists($assetsJsonPath)) {
            $entries = json_decode(file_get_contents($assetsJsonPath), true) ?? [];
        }
        
        $entryIndex = array_search($file, array_column($entries, 'file'));
        if ($entryIndex !== false) {
            $entries[$entryIndex] = array_merge($entries[$entryIndex], $data);
        } else {
            $entries[] = array_merge(['file' => $file], $data);
        }
        
        file_put_contents($assetsJsonPath, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function removeAssetEntry(string $projectId, string $file): void
    {
        $assetsJsonPath = config('aiev.paths.video_projects') . "/{$projectId}/assets/assets.json";
        if (!file_exists($assetsJsonPath)) return;
        
        $entries = json_decode(file_get_contents($assetsJsonPath), true) ?? [];
        $entries = array_values(array_filter($entries, fn($e) => ($e['file'] ?? '') !== $file));
        
        file_put_contents($assetsJsonPath, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function findAssetFile(string $projectId, string $file): ?string
    {
        $dir = config('aiev.paths.video_projects') . "/{$projectId}/assets";
        if (!is_dir($dir)) return null;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() === $file) {
                return $item->getPathname();
            }
        }
        return null;
    }

    /** PUT /api/v1/projects/{id}/assets/{file}/description */
    public function updateDescription(Request $request, string $id, string $file): JsonResponse
    {
        $desc = $request->input('description');
        if (!is_string($desc)) {
            return response()->json(['error' => ['code' => 'INVALID_DESCRIPTION', 'message' => 'description phải là string']], 400);
        }

        $assetPath = $this->findAssetFile($id, $file);
        if (!$assetPath || $file === 'assets.json') {
            return response()->json(['error' => ['code' => 'ASSET_NOT_FOUND', 'message' => 'Không tìm thấy asset']], 404);
        }

        $this->writeAssetEntry($id, $file, ['description' => $desc]);
        return response()->json([
            'name' => $file,
            'relPath' => str_replace(config('aiev.paths.video_projects') . "/{$id}/", "video-projects/{$id}/", $assetPath),
            'description' => $desc
        ]);
    }

    /** DELETE /api/v1/projects/{id}/assets/{file} */
    public function destroyAsset(string $id, string $file): JsonResponse
    {
        if ($file === 'assets.json') {
            return response()->json(['error' => ['code' => 'PROTECTED_FILE', 'message' => 'Không thể xóa assets.json']], 400);
        }

        $assetPath = $this->findAssetFile($id, $file);
        if (!$assetPath) {
            return response()->json(['error' => ['code' => 'ASSET_NOT_FOUND', 'message' => 'Không tìm thấy asset']], 404);
        }

        unlink($assetPath);
        $this->removeAssetEntry($id, $file);

        return response()->json(null, 204);
    }

    /** PUT /api/v1/projects/{id}/assets/{file}/grade */
    public function updateGrade(Request $request, string $id, string $file): JsonResponse
    {
        $preset = $request->input('preset');
        $adjust = $request->input('adjust');

        $data = [];
        if ($preset !== null) $data['colorGrade'] = $preset;
        if ($adjust !== null) $data['colorAdjust'] = $adjust;

        $this->writeAssetEntry($id, $file, $data);

        return response()->json(array_merge(['file' => $file], $data));
    }

    /** POST /api/v1/projects/{id}/assets/{file}/grade-preview */
    public function gradePreview(Request $request, string $id, string $file): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = \Illuminate\Support\Facades\Http::post("{$nodeWorker}/api/projects/{$id}/assets/{$file}/grade-preview", $request->all());
        return response()->json($response->json(), $response->status());
    }

    /** POST /api/v1/projects/{id}/assets/{file}/grade-frame */
    public function gradeFrame(Request $request, string $id, string $file): JsonResponse
    {
        $nodeWorker = config('aiev.node_worker_url');
        $response = \Illuminate\Support\Facades\Http::post("{$nodeWorker}/api/projects/{$id}/assets/{$file}/grade-frame", $request->all());
        return response()->json($response->json(), $response->status());
    }}
