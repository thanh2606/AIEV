<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Agent Skills.
 * Port từ apps/server/src/routes/skills.ts
 */
class SkillController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}
