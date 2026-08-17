<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast tiến độ render qua Reverb WebSocket.
 * Web UI lắng nghe channel 'render.{projectId}' để hiện progress bar 0-100%.
 */
class RenderProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $jobId,
        public string $projectId,
        public string $status,
        public int $progress,
        public string $step,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("render.{$this->projectId}"),
            new Channel('jobs'), // Global channel cho danh sách jobs
        ];
    }

    public function broadcastAs(): string
    {
        return 'render.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'jobId' => $this->jobId,
            'projectId' => $this->projectId,
            'status' => $this->status,
            'progress' => $this->progress,
            'step' => $this->step,
        ];
    }
}
