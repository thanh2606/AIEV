<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AievEvents;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Real-time SSE / Event Stream cho Web UI.
 * Port từ apps/server/src/index.ts /events.
 *
 * Web UI (apps/web/src/lib/useEvents.tsx) mở EventSource("/api/events") và
 * nghe 4 kênh: job, joblog, agent, upload. Stream giữ mở đến khi client ngắt;
 * giữa các lần đọc, loop block ngắn trên Redis Streams (AievEvents) rồi flush
 * ra SSE. Mỗi client tự nhớ cursor dạng "ms-seq" để đọc nốt phần đã lỡ.
 */
class EventController extends Controller
{
    /** Giây-tối đa một vòng lặp tĩnh lặng trước khi gửi heartbeat comment. */
    private const HEARTBEAT_MS = 15000;

    public function stream(): StreamedResponse
    {
        // Khoá host Redis cho dev khi chạy thẳng trên host (không Docker).
        $this->pointRedisAtDockerHost();

        return new StreamedResponse(function () {
            // Redis Streams: replay từ đầu (id "0") để tab mới không bỏ sót job
            // đang chạy; cursor sau mỗi lần đọc tự tiến lên.
            $lastId = '0';
            $lastRead = microtime(true) * 1000;

            while (!connection_aborted()) {
                $events = AievEvents::readAfter($lastId, 100, 500);
                foreach ($events as $e) {
                    $lastId = $e['id'];
                    $this->emit($e['event'], $e['payload']);
                    $lastRead = microtime(true) * 1000;
                }

                $now = microtime(true) * 1000;
                if ($now - $lastRead >= self::HEARTBEAT_MS) {
                    \ob_flush();
                    echo ": ping\n\n";
                    flush();
                    $lastRead = $now;
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function emit(string $event, mixed $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    /**
     * Khi chạy `php artisan serve` thẳng trên host (không Docker), config mặc định
     * trỏ Redis về 127.0.0.1 - đúng máy chạy Docker Compose công bố Redis 6379.
     * Nếu container node-worker dùng host.docker.internal thì tầng này chạy ngoài
     * host nên không đổi. Giữ nguyên để tường minh hai đường chạy.
     */
    private function pointRedisAtDockerHost(): void
    {
        // Nếu REDIS_HOST được đặt thành "redis" (tên service Docker) mà tiến trình
        // Laravel lại chạy ngoài container, thì tên đó không phân giải được.
        // Trong trường hợp đó chuyển về 127.0.0.1 của host.
        $host = (string) config('database.redis.default.host', '');
        if ($host === 'redis' && !$this->hostResolvable('redis')) {
            config()->set('database.redis.default.host', '127.0.0.1');
            Log::debug('[aiev.events] Redis host "redis" không phân giải được, đổi về 127.0.0.1 cho SSE.');
        }
    }

    private function hostResolvable(string $host): bool
    {
        return gethostbyname($host) !== $host;
    }
}
