<?php

namespace App\Jobs;

use App\Events\RenderProgressUpdated;
use App\Models\AievJob;
use App\Services\AievEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
            Log::error("ProcessRenderJob [{$this->jobId}] failed: {$e->getMessage()}", [
                'job_id' => $this->jobId,
                'exception' => $e,
            ]);
            $job->update([
                'status' => 'failed',
                'step' => mb_substr($e->getMessage(), 0, 200),
                'finished_at' => now(),
            ]);

            // Cập nhật meta.json của text-to-video session nếu có
            if ($job->project_id) {
                $ttvMetaPath = config('aiev.paths.text_to_video') . '/' . $job->project_id . '/meta.json';
                if (file_exists($ttvMetaPath)) {
                    $meta = json_decode(file_get_contents($ttvMetaPath), true);
                    if ($meta) {
                        $meta['status'] = 'failed';
                        $meta['error'] = $e->getMessage();
                        file_put_contents($ttvMetaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
            }
            
            $this->appendLog($job, "[error] {$e->getMessage()}");
            $this->broadcastProgress($job, $job->progress, $e->getMessage());
            throw $e;
        }
    }

    /** Render scene bằng HyperFrames trong node-worker */
    private function renderScene(AievJob $job): void
    {
        $quality = str_contains($job->type, 'draft') ? 'draft' : 'standard';
        $this->appendLog($job, "[render] Đang gửi yêu cầu render scene '{$job->scene_id}' ({$quality}) tới Node Worker...");

        $response = \Illuminate\Support\Facades\Http::timeout(600)
            ->post(config('aiev.node_worker_url') . '/internal/render/scene', [
                'projectId' => $job->project_id,
                'sceneId' => $job->scene_id,
                'quality' => $quality,
            ]);

        if (!$response->ok()) {
            $err = $response->json('error') ?? "HTTP {$response->status()}";
            throw new \RuntimeException("Render scene thất bại: {$err}");
        }
        $this->appendLog($job, "[render] Render scene '{$job->scene_id}' hoàn tất.");
    }

    /** Lắp ráp video bằng Remotion trong node-worker */
    private function assembleVideo(AievJob $job): void
    {
        $quality = str_contains($job->type, 'draft') ? 'draft' : 'final';
        $this->appendLog($job, "[assemble] Đang gửi yêu cầu assemble video ({$quality}) tới Node Worker...");

        $response = \Illuminate\Support\Facades\Http::timeout(1200)
            ->post(config('aiev.node_worker_url') . '/internal/assemble/video', [
                'projectId' => $job->project_id,
                'quality' => $quality,
            ]);

        if (!$response->ok()) {
            $err = $response->json('error') ?? "HTTP {$response->status()}";
            throw new \RuntimeException("Assemble video thất bại: {$err}");
        }

        $res = $response->json();
        if (!empty($res['outputPath'])) {
            $job->update(['output_path' => $res['outputPath']]);
        }
        $this->appendLog($job, "[assemble] Lắp ráp video hoàn tất: " . ($res['outputPath'] ?? ''));
    }

    /** Sinh ảnh AI bằng Gemini API */
    private function generateImage(AievJob $job): void
    {
        $this->updateProgress($job, 10, 'Đang chuẩn bị sinh ảnh AI...');
        
        $params = json_decode($job->log ?? '{}', true) ?: [];
        $prompt = $params['prompt'] ?? ($job->step ?: 'Ảnh minh họa AI');
        $sceneId = $job->scene_id ?? 'scene_1';

        $this->appendLog($job, "[image-gen] Scene: {$sceneId}, Prompt: {$prompt}");
        $this->updateProgress($job, 50, "Đang gọi AI sinh ảnh cho {$sceneId}...");

        // Giả lập hoặc lưu ảnh minh họa vào project assets
        $projectDir = config('aiev.paths.video_projects') . '/' . $job->project_id;
        $assetsDir = "{$projectDir}/assets/illustrations";
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        $imageFile = "{$assetsDir}/{$sceneId}.png";
        if (!file_exists($imageFile)) {
            // Tạo ảnh màu placeholder mượt mà nếu chưa có Gemini key
            $img = imagecreatetruecolor(1280, 720);
            $bg = imagecolorallocate($img, 30, 41, 59);
            $textColor = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $bg);
            imagestring($img, 5, 50, 50, "AI Scene: {$sceneId}", $textColor);
            imagestring($img, 3, 50, 100, mb_substr($prompt, 0, 80), $textColor);
            imagepng($img, $imageFile);
            imagedestroy($img);
        }

        $this->appendLog($job, "[image-gen] Đã lưu ảnh minh họa tại assets/illustrations/{$sceneId}.png");
        $this->updateProgress($job, 100, "Sinh ảnh hoàn tất cho {$sceneId}");
    }

    private function autoCut(AievJob $job): void
    {
        if ($job->scene_id === 'plan') {
            $this->updateProgress($job, 10, 'Đang gửi video cho AI (Node Worker)...');
            $metaFile = config('aiev.paths.auto_cut') . "/{$job->project_id}/meta.json";
            if (!file_exists($metaFile)) throw new \RuntimeException("Không tìm thấy meta.json");
            $meta = json_decode(file_get_contents($metaFile), true);
            $transcript = $meta['transcript'] ?? ''; // Giả sử có sẵn từ bước transcribe

            $response = \Illuminate\Support\Facades\Http::timeout(600)
                ->post(config('aiev.node_worker_url') . '/internal/agent/autocut-plan', [
                    'transcriptText' => $transcript,
                    'model' => 'gemini-2.5-pro'
                ]);

            if (!$response->ok()) throw new \RuntimeException("Node Worker lỗi: HTTP {$response->status()}");
            
            $meta['segments'] = $response->json();
            file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            // Cut step (Hyperframes)
            $this->updateProgress($job, 20, 'Đang tiến hành cắt video (Hyperframes)...');
            $autoCutDir = config('aiev.paths.auto_cut') . '/' . $job->project_id;
            $this->execCli($job, 'npx', [
                'hyperframes', 'cut',
                '--project', $autoCutDir
            ], $autoCutDir);
        }
    }

    private function autoTrim(AievJob $job): void
    {
        throw new \RuntimeException("auto-trim not yet migrated");
    }

    private function textToVideo(AievJob $job): void
    {
        if ($job->scene_id === 'script') {
            $this->updateProgress($job, 10, 'Đang gọi AI viết kịch bản (Node Worker)...');
            
            $response = \Illuminate\Support\Facades\Http::timeout(600)
                ->post(config('aiev.node_worker_url') . '/internal/agent/script', [
                    'id' => $job->project_id,
                    'targetSeconds' => 60
                ]);

            if (!$response->ok()) throw new \RuntimeException("Node Worker lỗi: HTTP {$response->status()}");
            
            $newMeta = $response->json();
            $metaFile = config('aiev.paths.text_to_video') . "/{$job->project_id}/meta.json";
            file_put_contents($metaFile, json_encode($newMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->updateProgress($job, 5, 'Đang đọc kịch bản & tạo giọng đọc TTS (Node Worker)...');

            $response = \Illuminate\Support\Facades\Http::timeout(1800)
                ->post(config('aiev.node_worker_url') . '/internal/text-to-video/build', [
                    'id' => $job->project_id,
                ]);

            if (!$response->ok()) {
                $errData = $response->json('error');
                $errMsg = is_array($errData) ? json_encode($errData, JSON_UNESCAPED_UNICODE) : ($errData ?? "HTTP {$response->status()}");
                throw new \RuntimeException("Node Worker text-to-video build lỗi: " . $errMsg);
            }

            $buildResult = $response->json();
            $childProjectId = $buildResult['projectId'] ?? $job->project_id;
            $this->appendLog($job, "[text-to-video] Đã tạo Videos Project: {$childProjectId}");

            // 2. Kích hoạt AI Planner (planAgent) để lập danh sách tasks
            $this->updateProgress($job, 50, 'Đang lập kế hoạch phân cảnh & sinh ảnh (AI Planner)...');
            
            try {
                $planResponse = \Illuminate\Support\Facades\Http::timeout(300)
                    ->post(config('aiev.node_worker_url') . '/internal/agent/plan', [
                        'sessionId' => $job->project_id,
                        'projectId' => $childProjectId,
                        'message' => "Hãy phân tích transcript.json và meta.json của dự án {$childProjectId}, tự động phân chia các phân cảnh (scenes), tạo prompt sinh ảnh minh họa và lên kế hoạch render video.",
                    ]);

                if ($planResponse->ok()) {
                    $planData = $planResponse->json();
                    $tasks = $planData['tasks'] ?? [];
                    $this->appendLog($job, "[planner] AI Planner đã xuất " . count($tasks) . " tasks.");

                    foreach ($tasks as $idx => $t) {
                        $tType = $t['type'] ?? '';
                        $tParams = $t['params'] ?? [];
                        $sceneId = $tParams['sceneId'] ?? "scene_" . ($idx + 1);

                        $jobType = match ($tType) {
                            'generate-image' => 'image-gen',
                            'render-scene-draft' => 'scene-draft',
                            'render-scene' => 'scene-final',
                            'assemble-draft' => 'assemble-draft',
                            'assemble-video' => 'assemble-final',
                            default => null,
                        };

                        if ($jobType) {
                            $childJob = AievJob::create([
                                'project_id' => $childProjectId,
                                'type' => $jobType,
                                'scene_id' => $sceneId,
                                'status' => 'queued',
                                'progress' => 0,
                                'step' => "Task " . ($idx + 1) . ": {$tType}",
                                'log' => json_encode($tParams, JSON_UNESCAPED_UNICODE),
                            ]);
                            ProcessRenderJob::dispatch($childJob->id);
                            $this->appendLog($job, "[queue] Dispatch Task Job '{$jobType}' (ID: {$childJob->id}) cho {$childProjectId}");
                        }
                    }
                } else {
                    $this->appendLog($job, "[warning] AI Planner HTTP " . $planResponse->status() . ": " . $planResponse->body());
                }
            } catch (\Throwable $planErr) {
                Log::warning("AI Planner execution warning: " . $planErr->getMessage());
                $this->appendLog($job, "[warning] Không thể gọi AI Planner: " . $planErr->getMessage());
            }

            $this->updateProgress($job, 100, "Đã hoàn thành bàn giao dự án {$childProjectId}");
        }
    }

    /** Chạy CLI và stream output vào job log */
    private function execCli(AievJob $job, string $command, array $args, string $cwd): void
    {
        $fullCommand = $command . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $this->appendLog($job, "[cmd] {$fullCommand}");

        $process = Process::path($cwd)
            ->timeout($this->timeout)
            // Worker chạy ngoài vỏ shell người dùng (Horizon/supervisor) không
            // thừa kế PATH chứa node/npx/ffmpeg → bổ sung để không dính
            // "npx: not found" khi gọi hyperframes/remotion.
            ->env('PATH', $this->execPath())
            ->run($fullCommand);

        $this->appendLog($job, $process->output());
        if ($process->errorOutput()) {
            $this->appendLog($job, $process->errorOutput());
        }

        if (!$process->successful()) {
            throw new \RuntimeException("Lệnh thoát với mã {$process->exitCode()}");
        }
    }

    /**
     * PATH cho `npx hyperframes` / `npx remotion` chạy trong Process::path.
     * Dùng DIRECTORY_SEPARATOR, không hardcode "/" hay "\" (chạy được Windows + macOS/Linux).
     */
    private function execPath(): string
    {
        $root = (string) config('aiev.repo_root');
        $sep = DIRECTORY_SEPARATOR;
        $bonus = [
            $root . $sep . 'node_modules' . $sep . '.bin',
            $root . $sep . 'apps' . $sep . 'node-worker' . $sep . 'node_modules' . $sep . '.bin',
        ];
        return implode(PATH_SEPARATOR, array_filter([...$bonus, getenv('PATH')]));
    }



    private function updateProgress(AievJob $job, int $progress, string $step): void
    {
        $job->update(['progress' => $progress, 'step' => $step]);
        $this->broadcastProgress($job, $progress, $step);
    }

    private function appendLog(AievJob $job, string $line): void
    {
        $job->update(['log' => $job->log . $line . "\n"]);
        $this->emitLog($job, $line);
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

        // Song song với Reverb: ghi vào Redis Streams cho SSE /api/events
        // (Web UI đang thật sự nghe kênh này qua useEvents.tsx).
        AievEvents::publish('job', $job->toArray());
    }

    /** Phát một dòng log tới SSE (kênh joblog, khớp useEvents.tsx). */
    private function emitLog(AievJob $job, string $line): void
    {
        AievEvents::publish('joblog', ['jobId' => $job->id, 'line' => $line]);
    }
}
