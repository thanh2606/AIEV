<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực AIEV API Token.
 * Port từ apps/server/src/index.ts (middleware xác thực).
 *
 * Cho qua khi:
 * - Loopback request (localhost trực tiếp)
 * - Header x-aiev-token khớp
 * - Cookie aiev_token khớp
 * - Query ?t= khớp
 */
class AievTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('aiev.api_token');

        if (!$token) {
            // Chưa cấu hình token → cho qua (dev mode)
            return $next($request);
        }

        // Loopback
        if (in_array($request->ip(), ['127.0.0.1', '::1', 'localhost'])) {
            return $next($request);
        }

        // Header
        $header = $request->header('x-aiev-token');
        if ($header && hash_equals($token, $header)) {
            return $next($request);
        }

        // Cookie
        $cookie = $request->cookie('aiev_token');
        if ($cookie && hash_equals($token, $cookie)) {
            return $next($request);
        }

        // Query
        $query = $request->query('t');
        if ($query && hash_equals($token, $query)) {
            return $next($request);
        }

        return response()->json([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Thiếu hoặc sai token truy cập',
            ],
        ], 401);
    }
}
