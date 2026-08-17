<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Quản lý phiên upload từ điện thoại qua QR code (/m).
 * Port từ apps/server/src/routes/uploadSession.ts
 */
class UploadSessionController extends Controller
{
    public function show(string $token): JsonResponse
    {
        return response()->json([
            'valid' => true,
            'token' => $token,
        ]);
    }
}
