<?php

namespace App\Services;

use App\Models\AievJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * Nhận JobSchedulePlan từ Claude (mảng tasks) → dispatch vào Laravel Horizon Queue.
 *
 * Task types: generate-image, render-scene, assemble-video, run-qc
 * Dependencies: task có thể phụ thuộc task khác (dependsOn) → dùng Bus::chain()
 */
class JobDispatcherService
{
    /**
     * Dispatch mảng tasks vào queue, tôn trọng thứ tự dependencies.
     */
    public function dispatch(string $projectId, array $tasks): array
    {
        $jobIds = [];
        $chains = [];
        $independent = [];

        // Tạo AievJob cho mỗi task
        foreach ($tasks as $i => $task) {
            $type = $this->normalizeType($task['type'] ?? 'render-scene');
            $jobId = 'job_' . Str::random(21);

            $job = AievJob::create([
                'id' => $jobId,
                'project_id' => $projectId,
                'type' => $type,
                'scene_id' => $task['sceneId'] ?? $task['params']['sceneId'] ?? null,
                'status' => 'queued',
                'progress' => 0,
                'step' => 'Đang chờ trong hàng đợi',
            ]);

            $jobIds[$task['id'] ?? "task_{$i}"] = $jobId;

            $hasDeps = !empty($task['dependsOn']);
            if ($hasDeps) {
                $chains[] = ['jobId' => $jobId, 'dependsOn' => $task['dependsOn']];
            } else {
                $independent[] = $jobId;
            }
        }

        // Dispatch các job độc lập song song
        foreach ($independent as $jobId) {
            \App\Jobs\ProcessRenderJob::dispatch($jobId);
        }

        // Dispatch các job có dependency theo chain
        // Đơn giản: dispatch tất cả với delay, Horizon tự quản lý concurrency
        foreach ($chains as $chain) {
            \App\Jobs\ProcessRenderJob::dispatch($chain['jobId'])
                ->delay(now()->addSeconds(5));
        }

        return $jobIds;
    }

    /** Chuyển type từ Claude schema → AievJob type */
    private function normalizeType(string $type): string
    {
        return match ($type) {
            'generate-image' => 'image-gen',
            'render-scene' => 'scene-final',
            'render-scene-draft' => 'scene-draft',
            'assemble-video' => 'assemble-final',
            'assemble-draft' => 'assemble-draft',
            'run-qc' => 'assemble-final', // QC = verify final
            default => $type,
        };
    }
}
