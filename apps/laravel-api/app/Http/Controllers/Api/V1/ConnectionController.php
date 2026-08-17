<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Quản lý kết nối AI - xem trạng thái + nhập/xóa API key.
 * Port từ apps/server/src/routes/connections.ts
 */
class ConnectionController extends Controller
{
    /** GET /api/v1/connections */
    public function index(): JsonResponse
    {
        return response()->json(['connections' => $this->listConnections()]);
    }

    /** PUT /api/v1/connections/{provider}/key */
    public function updateKey(Request $request, string $provider): JsonResponse
    {
        $envVars = [
            'gemini' => 'GEMINI_API_KEY',
            'claude' => 'ANTHROPIC_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'soniox' => 'SONIOX_API_KEY',
        ];

        if (!isset($envVars[$provider])) {
            return response()->json([
                'error' => ['code' => 'PROVIDER_NOT_FOUND', 'message' => "Không hỗ trợ provider \"{$provider}\""],
            ], 404);
        }

        $apiKey = $request->input('apiKey');
        if ($apiKey !== null && !is_string($apiKey)) {
            return response()->json([
                'error' => ['code' => 'INVALID_KEY', 'message' => 'apiKey phải là string hoặc null'],
            ], 400);
        }

        $envVar = $envVars[$provider];
        $trimmed = null;
        if (is_string($apiKey)) {
            $trimmed = trim($apiKey);
            $trimmed = preg_replace('/^["\']|["\']$/', '', $trimmed);
            $trimmed = preg_replace("/^{$envVar}\s*=\s*/i", '', $trimmed);
            $trimmed = trim($trimmed);
        }

        if ($trimmed !== null && strlen($trimmed) < 10) {
            return response()->json([
                'error' => ['code' => 'INVALID_KEY', 'message' => 'API key quá ngắn - kiểm tra lại (copy thiếu?)'],
            ], 400);
        }

        // Cập nhật .env file của AIEV root
        $this->upsertEnvVar($envVar, $trimmed);

        // Cập nhật runtime
        if ($trimmed === null) {
            putenv($envVar);
        } else {
            putenv("{$envVar}={$trimmed}");
        }

        return response()->json(['connections' => $this->listConnections()]);
    }

    /** POST /api/v1/connections/{provider}/test */
    public function test(Request $request, string $provider): JsonResponse
    {
        return match ($provider) {
            'gemini' => $this->testGemini(),
            'claude' => $this->testClaude(),
            'openai' => $this->testOpenai(),
            'soniox' => $this->testSoniox(),
            default => response()->json([
                'error' => ['code' => 'PROVIDER_NOT_FOUND', 'message' => "Không hỗ trợ provider \"{$provider}\""],
            ], 404),
        };
    }

    // ---- Private helpers ----

    private function listConnections(): array
    {
        $anthropicKey = config('aiev.anthropic_api_key', '');
        $proxyUrl = config('aiev.anthropic_base_url');
        $geminiKey = config('aiev.gemini_api_key', '');
        $openaiKey = config('aiev.openai_api_key', '');
        $sonioxKey = config('aiev.soniox_api_key', '');

        $home = env('HOME', env('USERPROFILE', ''));
        $configDir = env('CLAUDE_CONFIG_DIR', $home . '/.claude');
        $oauth = file_exists($configDir . '/.credentials.json');

        $claudeSource = $proxyUrl ? 'proxy' : ($oauth ? 'oauth' : ($anthropicKey ? 'api-key' : null));
        $claudeConnected = $proxyUrl || $oauth || $anthropicKey;

        return [
            [
                'id' => 'claude',
                'label' => 'Claude (Anthropic)',
                'roles' => ['edit', 'chat'],
                'connected' => $claudeConnected,
                'source' => $claudeSource,
                'note' => $this->claudeNote($proxyUrl, $oauth, $anthropicKey),
                'key' => [
                    'envVar' => 'ANTHROPIC_API_KEY',
                    'present' => !empty($anthropicKey),
                    'masked' => $anthropicKey ? $this->maskKey($anthropicKey) : null,
                ],
                'keyHelpUrl' => 'https://console.anthropic.com/settings/keys',
            ],
            [
                'id' => 'gemini',
                'label' => 'Gemini (Google)',
                'roles' => ['image'],
                'connected' => !empty($geminiKey),
                'source' => $geminiKey ? 'api-key' : null,
                'note' => $geminiKey
                    ? 'Đã kết nối bằng API key.'
                    : 'Chưa kết nối - nhập GEMINI_API_KEY để dùng tính năng Tạo ảnh.',
                'key' => [
                    'envVar' => 'GEMINI_API_KEY',
                    'present' => !empty($geminiKey),
                    'masked' => $geminiKey ? $this->maskKey($geminiKey) : null,
                ],
                'keyHelpUrl' => 'https://aistudio.google.com/apikey',
            ],
            [
                'id' => 'openai',
                'label' => 'OpenAI (ChatGPT)',
                'roles' => [],
                'connected' => !empty($openaiKey),
                'source' => $openaiKey ? 'api-key' : null,
                'note' => $openaiKey
                    ? 'Đã kết nối bằng API key.'
                    : 'Chưa kết nối.',
                'key' => [
                    'envVar' => 'OPENAI_API_KEY',
                    'present' => !empty($openaiKey),
                    'masked' => $openaiKey ? $this->maskKey($openaiKey) : null,
                ],
                'keyHelpUrl' => 'https://platform.openai.com/api-keys',
            ],
            [
                'id' => 'soniox',
                'label' => 'Soniox (bóc lời + phân vai người nói)',
                'roles' => ['stt'],
                'connected' => !empty($sonioxKey),
                'source' => $sonioxKey ? 'api-key' : null,
                'note' => $sonioxKey
                    ? 'Đã kết nối.'
                    : 'Chưa kết nối.',
                'key' => [
                    'envVar' => 'SONIOX_API_KEY',
                    'present' => !empty($sonioxKey),
                    'masked' => $sonioxKey ? $this->maskKey($sonioxKey) : null,
                ],
                'keyHelpUrl' => 'https://console.soniox.com',
            ],
        ];
    }

    private function maskKey(string $key): string
    {
        if (strlen($key) <= 12) return substr($key, 0, 3) . '…';
        return substr($key, 0, 6) . '…' . substr($key, -4);
    }

    private function claudeNote(?string $proxyUrl, bool $oauth, string $anthropicKey): string
    {
        if ($proxyUrl) {
            $suffix = $anthropicKey ? ' (có API key)' : ' (không cần API key)';
            return "Đang kết nối qua proxy/router: {$proxyUrl}{$suffix}";
        }
        if ($oauth) return 'Đang dùng subscription OAuth của Claude Code.';
        if ($anthropicKey) return 'Đang dùng API key (tính phí theo usage).';
        return 'Chưa kết nối.';
    }

    private function upsertEnvVar(string $name, ?string $value): void
    {
        $envFile = config('aiev.repo_root') . '/.env';
        if (!file_exists($envFile)) return;

        $lines = explode("\n", file_get_contents($envFile));
        $pattern = '/^\s*#?\s*' . preg_quote($name, '/') . '\s*=/';
        $filtered = array_filter($lines, fn ($l) => !preg_match($pattern, $l));

        // Bỏ dòng trống cuối
        while (count($filtered) > 0 && trim(end($filtered)) === '') {
            array_pop($filtered);
        }

        if ($value !== null) {
            $filtered[] = "{$name}={$value}";
        }

        file_put_contents($envFile, implode("\n", $filtered) . "\n");
    }

    private function testGemini(): JsonResponse
    {
        $key = config('aiev.gemini_api_key');
        if (!$key) {
            return response()->json(['ok' => false, 'message' => 'Chưa có GEMINI_API_KEY để test']);
        }

        try {
            $r = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(10)
                ->get('https://generativelanguage.googleapis.com/v1/models', ['pageSize' => 1]);

            if ($r->ok()) {
                return response()->json(['ok' => true, 'message' => 'Key Gemini hoạt động.']);
            }
            return response()->json(['ok' => false, 'message' => "Google từ chối (HTTP {$r->status()})."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Lỗi kết nối: ' . $e->getMessage()]);
        }
    }

    private function testClaude(): JsonResponse
    {
        $key = config('aiev.anthropic_api_key', '');
        $proxyUrl = config('aiev.anthropic_base_url');

        if ($key || $proxyUrl) {
            $baseUrl = rtrim($proxyUrl ?: 'https://api.anthropic.com', '/');
            $headers = ['anthropic-version' => '2023-06-01'];
            if ($key) $headers['x-api-key'] = $key;

            try {
                $r = Http::withHeaders($headers)->timeout(10)->get("{$baseUrl}/v1/models", ['limit' => 1]);
                if ($r->ok()) {
                    $msg = $proxyUrl ? "Proxy/router hoạt động: {$proxyUrl}" : 'API key Anthropic hoạt động.';
                    return response()->json(['ok' => true, 'message' => $msg]);
                }
                return response()->json(['ok' => false, 'message' => "Trả lỗi HTTP {$r->status()}."]);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => "Không kết nối được: {$e->getMessage()}"]);
            }
        }

        return response()->json(['ok' => false, 'message' => 'Chưa có xác thực Claude.']);
    }

    private function testOpenai(): JsonResponse
    {
        $key = config('aiev.openai_api_key');
        if (!$key) return response()->json(['ok' => false, 'message' => 'Chưa có OPENAI_API_KEY.']);

        try {
            $r = Http::withToken($key)->timeout(10)->get('https://api.openai.com/v1/models', ['limit' => 1]);
            return $r->ok()
                ? response()->json(['ok' => true, 'message' => 'API key OpenAI hoạt động.'])
                : response()->json(['ok' => false, 'message' => "Key không hợp lệ (HTTP {$r->status()})."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function testSoniox(): JsonResponse
    {
        $key = config('aiev.soniox_api_key');
        if (!$key) return response()->json(['ok' => false, 'message' => 'Chưa có SONIOX_API_KEY.']);

        try {
            $r = Http::withToken($key)->timeout(10)->get('https://api.soniox.com/v1/models');
            return $r->ok()
                ? response()->json(['ok' => true, 'message' => 'Key Soniox hoạt động.'])
                : response()->json(['ok' => false, 'message' => "Soniox từ chối (HTTP {$r->status()})."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
