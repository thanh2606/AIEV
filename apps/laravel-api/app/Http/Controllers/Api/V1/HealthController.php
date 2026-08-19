<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Health check endpoint - public, không cần auth.
 * Port từ apps/server/src/routes/health.ts
 */
class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $repoRoot = config('aiev.repo_root');

        return response()->json([
            'status' => 'ok',
            'version' => '0.1.0',
            'architecture' => 'hybrid-laravel',
            'services' => [
                'laravel' => true,
                'redis' => $this->checkRedis(),
                'node_worker' => $this->checkNodeWorker(),
            ],
            'repo_root' => $repoRoot,
            'api_token' => config('aiev.api_token'),
            'claude' => $this->claudeStatus(),
        ]);
    }

    private function checkRedis(): bool
    {
        try {
            return Redis::ping() === true || Redis::ping() === 'PONG';
        } catch (\Throwable $e) {
            Log::warning("HealthCheck Redis failed: {$e->getMessage()}", ['exception' => $e]);
            return false;
        }
    }

    private function checkNodeWorker(): bool
    {
        try {
            $response = Http::timeout(3)
                ->get(config('aiev.node_worker_url') . '/health');
            return $response->ok();
        } catch (\Throwable $e) {
            Log::warning("HealthCheck NodeWorker failed: {$e->getMessage()}", ['exception' => $e]);
            return false;
        }
    }

    private function claudeStatus(): array
    {
        $hasApiKey = !empty(config('aiev.anthropic_api_key'));
        $hasProxy = !empty(config('aiev.anthropic_base_url'));

        // Kiểm tra OAuth credentials trên máy
        $home = env('HOME', env('USERPROFILE', ''));
        $configDir = env('CLAUDE_CONFIG_DIR', $home . '/.claude');
        $hasOauth = file_exists($configDir . '/.credentials.json');

        return [
            'connected' => $hasApiKey || $hasProxy || $hasOauth,
            'source' => $hasProxy ? 'proxy' : ($hasOauth ? 'oauth' : ($hasApiKey ? 'api-key' : null)),
            'model' => config('aiev.anthropic_model'),
        ];
    }
}
