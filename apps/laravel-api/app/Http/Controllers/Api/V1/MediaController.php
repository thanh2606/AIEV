<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;

/**
 * Phục vụ static media files từ repoRoot (/media/* và /api/media/*).
 * Port từ apps/server/src/routes/media.ts & index.ts
 */
class MediaController extends Controller
{
    public function show(string $path): BinaryFileResponse|JsonResponse
    {
        $repoRoot = config('aiev.repo_root');

        // Ngăn chặn directory traversal
        $normalizedPath = ltrim(str_replace(['..', '\\'], ['', '/'], $path), '/');
        $fullPath = "{$repoRoot}/{$normalizedPath}";

        if (!file_exists($fullPath) || is_dir($fullPath)) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => "Media file \"{$path}\" không tồn tại"],
            ], 404);
        }

        // Detect mime type
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $headers = [
            'Content-Type' => $mime,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=86400',
        ];

        return response()->file($fullPath, $headers);
    }
}
