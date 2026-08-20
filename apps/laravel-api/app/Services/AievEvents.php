<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Cầu nối sự kiện realtime cho Web UI qua SSE (GET /api/events).
 *
 * Web UI vẫn dùng EventSource("/api/events") với 4 kênh: job, joblog, agent,
 * upload (xem apps/web/src/lib/useEvents.tsx). Tầng này thay thế SSE hub
 * in-process của backend cũ (apps/server/src/events.ts) bằng Redis Streams:
 *
 *  - publish(): worker (Horizon) ghi event vào stream `aiev.events`.
 *  - publishLoop(): EventController đọc nốt từ cursor của từng client.
 *
 * Dùng Streams thay vì Pub/Sub vì một lý do: Pub/Sub không giữ lại event cho
 * client mới mở; Streams giữ một khoảng đủ để tab mới đọc nốt đã lỡ bằng
 * cursor "0-0", không bỏ sót. Bội dung tối đa giữ lại do MAXLEN giới hạn.
 */
class AievEvents
{
    /** Tên stream Redis chứa event. */
    public const STREAM = 'aiev.events';

    /** Giữ tối đa ~5000 event - chỉ là nguồn replay ngắn cho tab mới mở. */
    public const MAXLEN = 5000;

    /**
     * Đẩy một event lên stream. Payload phải là array/object - tự JSON hoá.
     */
    public static function publish(string $event, mixed $data): void
    {
        try {
            Redis::connection()->xAdd(
                self::STREAM,
                '*',
                ['event' => $event, 'payload' => json_encode($data, JSON_UNESCAPED_UNICODE)],
                self::MAXLEN,
                true,
            );
        } catch (\Throwable $e) {
            // Redis chưa chạy (đang dev không Docker): SSE chỉ mất tiến độ mềm,
            // không được làm sập worker render. REST vẫn trả về trạng thái thật.
            \Illuminate\Support\Facades\Log::debug("[aiev.events] publish thất bại: {$e->getMessage()}");
        }
    }

    /**
     * Đọc max ~$count event mới sau $lastId. Trả về [id, event, payload].
     * $block tính bằng mili-giây - truyền null để không chặn.
     *
     * @return array<int, array{id: string, event: string, payload: mixed}>
     */
    public static function readAfter(string $lastId, int $count = 100, ?int $block = null): array
    {
        try {
            $raw = $block === null
                ? Redis::connection()->xRead([self::STREAM => $lastId], ['count' => $count])
                : Redis::connection()->xRead([self::STREAM => $lastId], ['count' => $count, 'block' => $block]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::debug("[aiev.events] read thất bại: {$e->getMessage()}");
            return [];
        }

        if (!$raw) {
            return [];
        }

        $out = [];
        foreach ($raw as $stream => $messages) {
            foreach ($messages as $id => $fields) {
                $payload = $fields['payload'] ?? 'null';
                $out[] = [
                    'id' => (string) $id,
                    'event' => (string) ($fields['event'] ?? 'message'),
                    'payload' => json_decode($payload, true) ?? $payload,
                ];
            }
        }
        return $out;
    }
}
