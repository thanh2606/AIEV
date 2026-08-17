<?php

namespace App\Jobs;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\JobDispatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xử lý chat message - gửi tới Node Worker cho Claude suy luận 1 turn,
 * rồi dispatch JobSchedulePlan vào Horizon Queue.
 */
class ProcessChatMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public string $sessionId,
        public string $message,
    ) {
        $this->onQueue('chat');
    }

    public function handle(JobDispatcherService $dispatcher): void
    {
        $session = ChatSession::find($this->sessionId);
        if (!$session) return;

        try {
            $model = config('aiev.anthropic_base_url') 
                ? config('aiev.anthropic_model') 
                : ($session->model ?? config('aiev.anthropic_model'));

            // Gửi đến Node Worker: Claude suy luận 1 turn → trả JSON
            $response = Http::timeout($this->timeout)
                ->post(config('aiev.node_worker_url') . '/internal/agent/plan', [
                    'sessionId' => $this->sessionId,
                    'projectId' => $session->project_id,
                    'message' => $this->message,
                    'model' => $model,
                    'effort' => $session->effort,
                ]);

            if (!$response->ok()) {
                throw new \RuntimeException("Node Worker lỗi: HTTP {$response->status()}");
            }

            $result = $response->json();

            // Lưu response của AI
            ChatMessage::create([
                'session_id' => $this->sessionId,
                'role' => 'assistant',
                'kind' => 'text',
                'content' => $result['text'] ?? json_encode($result),
                'created_at' => now(),
            ]);

            // Nếu có JobSchedulePlan → dispatch vào queue
            if (isset($result['tasks']) && is_array($result['tasks'])) {
                $dispatcher->dispatch($session->project_id, $result['tasks']);
            }

            $session->update(['status' => 'done']);
            $session->finishRun();

        } catch (\Throwable $e) {
            Log::error("ProcessChatMessage failed: {$e->getMessage()}", [
                'sessionId' => $this->sessionId,
            ]);

            ChatMessage::create([
                'session_id' => $this->sessionId,
                'role' => 'assistant',
                'kind' => 'text',
                'content' => "Lỗi: {$e->getMessage()}",
                'created_at' => now(),
            ]);

            $session->update(['status' => 'error']);
            $session->finishRun();
        }
    }
}
