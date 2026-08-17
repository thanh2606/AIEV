<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Thư viện logo brand.
 * Port từ apps/server/src/routes/brandLogos.ts
 */
class BrandLogoController extends Controller
{
    /** GET /api/v1/brand-logos */
    public function index(): JsonResponse
    {
        $dir = config('aiev.paths.assets') . '/brand-logos';
        $logos = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f !== '.' && $f !== '..' && str_ends_with($f, '.svg')) {
                    $name = pathinfo($f, PATHINFO_FILENAME);
                    $logos[] = [
                        'slug' => Str::slug($name),
                        'title' => ucfirst($name),
                        'color' => '#000000',
                        'file' => $f,
                        'relPath' => "assets/brand-logos/{$f}",
                    ];
                }
            }
        }
        return response()->json($logos);
    }

    /** POST /api/v1/brand-logos */
    public function store(Request $request): JsonResponse
    {
        $name = trim($request->input('name', ''));
        if (empty($name)) {
            return response()->json(['error' => ['code' => 'BRAND_NAME_REQUIRED', 'message' => 'Thiếu tên brand']], 400);
        }

        $slug = Str::slug($name);
        $file = "{$slug}.svg";
        $relPath = "assets/brand-logos/{$file}";

        return response()->json([
            'slug' => $slug,
            'title' => ucfirst($name),
            'color' => '#000000',
            'file' => $file,
            'relPath' => $relPath,
            'source' => 'custom',
            'added' => true,
        ], 201);
    }
}
