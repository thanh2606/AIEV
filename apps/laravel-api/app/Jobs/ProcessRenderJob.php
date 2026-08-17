<?php

namespace App\Jobs;

use App\Events\RenderProgressUpdated;
use App\Models\AievJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;

/**
 * PHP Worker xử lý render job - chạy trên Laravel Horizon Queue.
 * Thay thế RenderQueue in-process của Node.js (queue.ts).
 */
class ProcessRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; // 30 phút max cho render

    public function __construct(
        public string $jobId,
    ) {
        $this->onQueue('render');
    }

    public function handle(): void
    {
        $job = AievJob::find($this->jobId);
        if (!$job || $job->status !== 'queued') return;

        $job->update([
            'status' => 'running',
            'started_at' => now(),
            'step' => 'Bắt đầu',
            'progress' => 0,
        ]);

        $this->broadcastProgress($job, 0, 'Bắt đầu');

        try {
            match ($job->type) {
                'scene-draft', 'scene-final' => $this->renderScene($job),
                'assemble-draft', 'assemble-final' => $this->assembleVideo($job),
                'image-gen' => $this->generateImage($job),
                'auto-cut' => $this->autoCut($job),
                'auto-trim' => $this->autoTrim($job),
                'text-to-video' => $this->textToVideo($job),
                'translate-video' => $this->translateVideo($job),
                default => throw new \RuntimeException("Unknown job type: {$job->type}"),
            };

            $job->update([
                'status' => 'done',
                'progress' => 100,
                'step' => 'Hoàn thành',
                'finished_at' => now(),
            ]);
            $this->broadcastProgress($job, 100, 'Hoàn thành');

        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'step' => mb_substr($e->getMessage(), 0, 200),
                'finished_at' => now(),
            ]);
            $this->appendLog($job, "[error] {$e->getMessage()}");
            $this->broadcastProgress($job, $job->progress, $e->getMessage());
            throw $e;
        }
    }

    /** Render scene bằng HyperFrames CLI */
    private function renderScene(AievJob $job): void
    {
        $repoRoot = config('aiev.repo_root');
        $projectDir = config('aiev.paths.video_projects') . '/' . $job->project_id;
        $quality = str_contains($job->type, 'draft') ? 'draft' : 'standard';
        $output = "renders/{$quality}.mp4";

        if ($job->scene_id) {
            $output = "renders/{$job->scene_id}-{$quality}.mp4";
        }

        $this->execCli($job, 'npx', [
            'hyperframes', 'render',
            '--quality', $quality,
            '--output', $output,
        ], $projectDir);
    }

    /** Lắp ráp video bằng Remotion CLI */
    private function assembleVideo(AievJob $job): void
    {
        $repoRoot = config('aiev.repo_root');
        $remotionDir = config('aiev.paths.engines_remotion');
        $quality = str_contains($job->type, 'draft') ? 'draft' : 'final';

        $this->execCli($job, 'npx', [
            'remotion', 'render',
            'Main',
            '--props', "{$job->project_id}/props.resolved.json",
            '--output', "../../outputs/{$job->project_id}-{$quality}.mp4",
        ], $remotionDir);
    }

    /** Sinh ảnh AI bằng Gemini API */
    private function generateImage(AievJob $job): void
    {
        $this->updateProgress($job, 10, 'Gọi Gemini API...');
        // TODO: Implement Gemini image generation via HTTP
        // Tạm thời dispatch tới Node Worker
        $this->delegateToNodeWorker($job, 'generate-image');
    }

    private function autoCut(AievJob $job): void
    {
        $this->delegateToNodeWorker($job, 'auto-cut');
    }

    private function autoTrim(AievJob $job): void
    {
        $this->delegateToNodeWorker($job, 'auto-trim');
    }

    private function textToVideo(AievJob $job): void
    {
        $this->delegateToNodeWorker($job, 'text-to-video');
    }

    private function translateVideo(AievJob $job): void
    {
        $this->delegateToNodeWorker($job, 'translate-video');
    }

    /** Chạy CLI và stream output vào job log */
    private function execCli(AievJob $job, string $command, array $args, string $cwd): void
    {
        $fullCommand = $command . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $this->appendLog($job, "[cmd] {$fullCommand}");

        $process = Process::path($cwd)
            ->timeout($this->timeout)
            ->run($fullCommand);

        $this->appendLog($job, $process->output());
        if ($process->errorOutput()) {
            $this->appendLog($job, $process->errorOutput());
        }

        if (!$process->successful()) {
            throw new \RuntimeException("Lệnh thoát với mã {$process->exitCode()}");
        }
    }

    /** Delegate job phức tạp cho Node Worker (backward compatible) */
    private function delegateToNodeWorker(AievJob $job, string $type): void
    {
        $this->updateProgress($job, 5, "Chuyển tiếp cho Node Worker ({$type})...");

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(1800)
                ->post(config('aiev.node_worker_url') . '/internal/job/execute', [
                    'jobId' => $job->id,
                    'type' => $type,
                    'projectId' => $job->project_id,
                    'sceneId' => $job->scene_id,
                ]);

            if (!$response->ok()) {
                throw new \RuntimeException("Node Worker trả lỗi: HTTP {$response->status()}");
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException("Không kết nối được Node Worker: {$e->getMessage()}");
        }
    }

    private function updateProgress(AievJob $job, int $progress, string $step): void
    {
        $job->update(['progress' => $progress, 'step' => $step]);
        $this->broadcastProgress($job, $progress, $step);
    }

    private function appendLog(AievJob $job, string $line): void
    {
        $job->update(['log' => $job->log . $line . "\n"]);
    }

    private function broadcastProgress(AievJob $job, int $progress, string $step): void
    {
        broadcast(new RenderProgressUpdated(
            $job->id,
            $job->project_id,
            $job->status,
            $progress,
            $step,
        ));
    }
}
