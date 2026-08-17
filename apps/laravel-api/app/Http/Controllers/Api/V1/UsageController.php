<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json(TokenUsage::totals());
    }

    public function timeline(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $scope = $request->query('scope', 'all');
        return response()->json(TokenUsage::timeline($days, $scope));
    }

    public function byProject(): JsonResponse
    {
        return response()->json(TokenUsage::tokensByProject());
    }
}
