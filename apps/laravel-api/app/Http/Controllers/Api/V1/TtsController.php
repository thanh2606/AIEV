<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Text-to-speech synthesis & Voice endpoints.
 * Port từ apps/server/src/routes/tts.ts
 */
class TtsController extends Controller
{
    /** GET /api/v1/tts/engines */
    public function engines(): JsonResponse
    {
        $geminiKey = config('aiev.gemini_api_key');
        $hasGemini = !empty($geminiKey);

        return response()->json([
            [
                'engine' => 'gemini',
                'available' => $hasGemini,
                'canClone' => false,
                'reason' => $hasGemini ? null : 'NO_GEMINI_KEY',
                'detail' => $hasGemini ? 'GEMINI_API_KEY đã được cấu hình' : 'Chưa có GEMINI_API_KEY trong .env',
            ],
            [
                'engine' => 'vieneu',
                'available' => true,
                'canClone' => true,
                'reason' => null,
                'detail' => 'ViENEU Local Engine',
            ],
        ]);
    }

    /** GET /api/v1/tts/models */
    public function models(): JsonResponse
    {
        return response()->json([
            ['id' => 'gemini-2.5-flash-preview-tts', 'label' => 'Flash TTS (khuyên dùng, rẻ nhất) - gemini-2.5-flash-preview-tts'],
            ['id' => 'gemini-2.5-pro-preview-tts', 'label' => 'Pro TTS (đắt hơn 2,6 lần) - gemini-2.5-pro-preview-tts'],
            ['id' => 'gemini-3.1-flash-tts-preview', 'label' => 'Flash TTS 3.1 (nhịp đọc đều hơn) - gemini-3.1-flash-tts-preview'],
        ]);
    }

    /** GET /api/v1/tts/voices */
    public function voices(Request $request): JsonResponse
    {
        $want = $request->query('engine');
        $query = \App\Models\TtsVoice::query();
        
        if ($want) {
            $query->where('engine', $want);
        }
        
        $voices = $query->get()->map(function ($v) {
            return [
                'engine' => $v->engine,
                'name' => $v->name,
                'title' => $v->title,
                'label' => $v->label,
                'gender' => $v->gender,
                'f0' => $v->f0,
                'kind' => $v->kind,
                'region' => $v->region,
                'timbreKey' => $v->timbre_key,
                'note' => $v->note ?? '',
            ];
        });

        return response()->json($voices);
    }

    /** GET /api/v1/tts/languages */
    public function languages(): JsonResponse
    {
        return response()->json([
            ['code' => 'vi-VN', 'label' => 'Tiếng Việt (Việt Nam)'],
            ['code' => 'en-US', 'label' => 'English (US)'],
            ['code' => 'en-GB', 'label' => 'English (UK)'],
            ['code' => 'ja-JP', 'label' => '日本語 (Nhật)'],
            ['code' => 'ko-KR', 'label' => '한국어 (Hàn)'],
            ['code' => 'zh-CN', 'label' => '中文 (Trung)'],
        ]);
    }

    /** POST /api/v1/tts/preview */
    public function preview(Request $request)
    {
        $engine = $request->input('engine', 'gemini');
        $text = $request->input('text', 'Đây là hệ Edit video AI by noi ti chấm vn');
        $voice = $request->input('voice');

        if (!$voice) {
            return response()->json(['error' => ['code' => 'VOICE_REQUIRED', 'message' => 'Thiếu tên giọng cần nghe thử']], 400);
        }

        if ($engine === 'vieneu') {
            // Proxy to node-worker
            $nodeWorker = config('aiev.node_worker_url');
            try {
                $res = Http::withBody($request->getContent(), 'application/json')
                    ->post("{$nodeWorker}/api/tts/preview");
                if ($res->successful()) {
                    return response($res->body(), $res->status())
                        ->header('Content-Type', 'audio/wav')
                        ->header('x-tts-model', $res->header('x-tts-model') ?? 'vieneu')
                        ->header('x-tts-duration', $res->header('x-tts-duration') ?? '0');
                }
            } catch (\Throwable $e) {
                return response()->json(['error' => ['code' => 'NODE_WORKER_ERROR', 'message' => 'Lỗi kết nối node-worker: ' . $e->getMessage()]], 500);
            }
        } else {
            // Gemini Native PHP
            $geminiKey = config('aiev.gemini_api_key');
            if (!$geminiKey) {
                return response()->json(['error' => ['code' => 'NO_GEMINI_KEY', 'message' => 'Chưa có GEMINI_API_KEY']], 503);
            }

            $model = $request->input('model', 'gemini-2.5-flash-preview-tts');
            $language = $request->input('language');

            $payload = [
                'contents' => [
                    ['parts' => [['text' => $text]]]
                ],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => [
                                'voiceName' => $voice
                            ]
                        ]
                    ]
                ]
            ];
            
            if ($language) {
                $payload['generationConfig']['speechConfig']['languageCode'] = $language;
            }

            try {
                $res = Http::withHeaders(['x-goog-api-key' => $geminiKey])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);

                if ($res->successful()) {
                    $data = $res->json();
                    $b64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
                    if ($b64) {
                        $pcm = base64_decode($b64);
                        
                        // Add 44-byte WAV header for 24000Hz mono 16-bit PCM
                        $sampleRate = 24000;
                        $channels = 1;
                        $byteRate = $sampleRate * $channels * 2;
                        $header = pack("A4V A4A4V v V V v v A4V", 
                            "RIFF", 36 + strlen($pcm), "WAVE", "fmt ", 16, 1, $channels, $sampleRate, $byteRate, $channels * 2, 16, "data", strlen($pcm)
                        );
                        
                        $wav = $header . $pcm;
                        $durationSec = strlen($pcm) / $byteRate;

                        return response($wav)
                            ->header('Content-Type', 'audio/wav')
                            ->header('x-tts-model', $model)
                            ->header('x-tts-duration', number_format($durationSec, 2));
                    }
                }
                
                return response()->json(['error' => ['code' => 'GEMINI_TTS_FAILED', 'message' => 'Gemini API lỗi: ' . $res->body()]], 502);
            } catch (\Throwable $e) {
                return response()->json(['error' => ['code' => 'HTTP_ERROR', 'message' => 'Lỗi kết nối Gemini: ' . $e->getMessage()]], 500);
            }
        }

        return response()->json(['error' => ['code' => 'TTS_PREVIEW_FAILED', 'message' => 'Tạo âm thanh thất bại']], 500);
    }
}
