<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Prompt Templates.
 * Port từ apps/server/src/routes/prompts.ts
 */
class PromptController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}
