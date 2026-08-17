<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Real-time SSE / Event Stream fallback cho Web UI.
 * Port từ apps/server/src/index.ts /events
 */
class EventController extends Controller
{
    public function stream(): StreamedResponse
    {
        return new StreamedResponse(function () {
            echo "retry: 5000\n\n";
            echo "event: ping\ndata: {\"time\":\"" . now()->toISOString() . "\"}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
