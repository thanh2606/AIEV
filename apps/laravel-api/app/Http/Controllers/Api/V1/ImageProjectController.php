<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Quản lý Image Projects.
 * Port từ apps/server/src/routes/images.ts
 */
class ImageProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $dir = config('aiev.paths.image_projects');
        if (!is_dir($dir)) return response()->json([]);

        $projects = [];
        foreach (scandir($dir) as $d) {
            if ($d === '.' || $d === '..') continue;
            $metaFile = "{$dir}/{$d}/meta.json";
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                if ($meta) $projects[] = $meta;
            }
        }

        return response()->json($projects);
    }
}
