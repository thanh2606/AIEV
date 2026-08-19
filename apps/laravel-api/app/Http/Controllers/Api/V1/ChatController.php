<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chat với Claude Agent.
 * Port từ apps/server/src/routes/chat.ts
 *
 * Trong kiến trúc hybrid, controller này gửi yêu cầu sang Node.js Worker
 * (POST /internal/agent/plan) thay vì chạy Agent SDK trực tiếp.
 */
class ChatController extends Controller
{
    /** GET /api/v1/chat/sessions */
    public function sessions(Request $request): JsonResponse
    {
        $projectId = $request->query('projectId');

        $query = ChatSession::orderByDesc('updated_at');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $sessions = $query->get()->map(function ($s) {
            $data = $s->toArray();
            $data['autoResume'] = (bool) $s->auto_resume;
            unset($data['auto_resume']);
            return $data;
        });

        return response()->json($sessions);
    }

    /** GET /api/v1/chat/{sessionId}/messages */
    public function messages(string $sessionId): JsonResponse
    {
        ChatSession::findOrFail($sessionId);

        $messages = ChatMessage::where('session_id', $sessionId)
            ->orderBy('id')
            ->get(['role', 'kind', 'content', 'created_at']);

        // Legacy format mapping: created_at -> createdAt
        $formatted = $messages->map(function ($m) {
            return [
                'role' => $m->role,
                'kind' => $m->kind,
                'content' => $m->content,
                'createdAt' => $m->created_at,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * POST /api/v1/chat
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|min:1',
            'sessionId' => 'nullable|string',
            'projectId' => 'nullable|string',
            'model' => 'nullable|string',
            'effort' => 'nullable|string',
        ]);

        $message = $request->input('message');
        $sessionId = $request->input('sessionId');

        if ($sessionId) {
            $session = ChatSession::findOrFail($sessionId);
            
            // Cập nhật model/effort nếu có
            if ($request->has('model') || $request->has('effort')) {
                $patch = [];
                if ($request->has('model')) $patch['model'] = $request->input('model');
                if ($request->has('effort')) $patch['effort'] = $request->input('effort');
                $session->update($patch);
            }
        } else {
            $sessionId = 'sess_' . \Illuminate\Support\Str::random(21);
            $session = ChatSession::create([
                'session_id' => $sessionId,
                'title' => mb_substr($message, 0, 60),
                'project_id' => $request->input('projectId'),
                'model' => $request->input('model'),
                'effort' => $request->input('effort'),
                'status' => 'idle',
            ]);
        }

        // Lưu message của user
        ChatMessage::create([
            'session_id' => $sessionId,
            'role' => 'user',
            'kind' => 'text',
            'content' => $message,
            'created_at' => now(),
        ]);

        $session->update(['status' => 'running']);
        $session->startRun();

        // Gửi yêu cầu sang Node Worker (async)
        dispatch(new \App\Jobs\ProcessChatMessage($sessionId, $message));

        return response()->json([
            'sessionId' => $sessionId,
        ], 202);
    }

    /** PUT /api/v1/chat/{sessionId}/auto-resume */
    public function updateAutoResume(Request $request, string $sessionId): JsonResponse
    {
        $session = ChatSession::findOrFail($sessionId);

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $session->update(['auto_resume' => $request->input('enabled')]);

        return response()->json(null, 204);
    }

    /** POST /api/v1/chat/{sessionId}/interrupt */
    public function interrupt(string $sessionId): JsonResponse
    {
        $session = ChatSession::findOrFail($sessionId);

        if ($session->status !== 'running') {
            // Idempotent: Ignore if not running
            return response()->json(['interrupted' => true], 204);
        }

        // Gửi interrupt request đến Node Worker
        try {
            Http::timeout(5)->post(config('aiev.node_worker_url') . '/internal/agent/interrupt', [
                'sessionId' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            Log::warning("ChatController interrupt error for session {$sessionId}: {$e->getMessage()}", ['exception' => $e]);
        }

        $session->update(['status' => 'interrupted']);
        $session->finishRun();

        return response()->json(['interrupted' => true], 204);
    }
}
