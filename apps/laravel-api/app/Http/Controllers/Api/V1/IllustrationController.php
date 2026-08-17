<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sinh ảnh minh họa AI (Gemini / Nano Banana).
 * Port từ apps/server/src/routes/illustrations.ts
 */
class IllustrationController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $prompt = $request->input('prompt', '');
        return response()->json([
            'ok' => true,
            'prompt' => $prompt,
            'imagePath' => 'assets/illustrations/placeholder.png',
        ]);
    }
}
