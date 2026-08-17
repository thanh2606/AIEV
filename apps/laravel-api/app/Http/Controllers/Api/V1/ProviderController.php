<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Danh sách AI providers & models.
 * Port từ apps/server/src/routes/providers.ts
 */
class ProviderController extends Controller
{
    public function index(): JsonResponse
    {
        $hasClaudeKey = !empty(config('aiev.anthropic_api_key'));
        $hasClaudeProxy = !empty(config('aiev.anthropic_base_url'));
        $hasGeminiKey = !empty(config('aiev.gemini_api_key'));

        return response()->json([
            [
                'id' => 'claude',
                'label' => 'Claude (Anthropic)',
                'connected' => $hasClaudeKey || $hasClaudeProxy,
                'source' => $hasClaudeProxy ? 'proxy' : ($hasClaudeKey ? 'api-key' : null),
                'roles' => ['edit', 'chat'],
                'models' => [
                    ['id' => 'claude-3-7-sonnet-latest', 'label' => 'Claude 3.7 Sonnet'],
                    ['id' => 'claude-3-5-haiku-latest', 'label' => 'Claude 3.5 Haiku'],
                ],
            ],
            [
                'id' => 'gemini',
                'label' => 'Gemini (Google)',
                'connected' => $hasGeminiKey,
                'source' => $hasGeminiKey ? 'api-key' : null,
                'roles' => ['image'],
                'models' => [
                    ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash'],
                    ['id' => 'imagen-3.0-generate-002', 'label' => 'Imagen 3.0'],
                ],
            ],
        ]);
    }
}
