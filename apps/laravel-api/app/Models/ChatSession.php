<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phiên chat Claude Agent.
 *
 * Port từ apps/server/src/db.ts (ChatSessionRow interface).
 */
class ChatSession extends Model
{
    protected $table = 'chat_sessions';
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUSES = ['idle', 'running', 'done', 'error', 'interrupted'];

    protected $fillable = [
        'session_id',
        'sdk_session_id',
        'title',
        'project_id',
        'status',
        'model',
        'effort',
        'run_started_at',
        'run_finished_at',
        'auto_resume',
        'resume_attempts',
        'goal',
        'progress_mark',
    ];

    protected $casts = [
        'auto_resume' => 'boolean',
        'resume_attempts' => 'integer',
        'run_started_at' => 'datetime',
        'run_finished_at' => 'datetime',
    ];

    protected $hidden = ['sdk_session_id'];

    public function toArray(): array
    {
        return [
            'sessionId' => $this->session_id,
            'title' => $this->title,
            'projectId' => $this->project_id,
            'status' => $this->status,
            'model' => $this->model,
            'effort' => $this->effort,
            'runStartedAt' => $this->run_started_at?->toISOString(),
            'runFinishedAt' => $this->run_finished_at?->toISOString(),
            'autoResume' => (bool) $this->auto_resume,
            'resumeAttempts' => (int) $this->resume_attempts,
            'goal' => $this->goal,
            'progressMark' => $this->progress_mark,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id', 'session_id')
            ->orderBy('id');
    }

    public function startRun(): void
    {
        $this->update([
            'run_started_at' => now(),
            'run_finished_at' => null,
            'resume_attempts' => 0,
        ]);
    }

    public function finishRun(): void
    {
        $this->update(['run_finished_at' => now()]);
    }

    public function bumpResumeAttempts(): int
    {
        $this->increment('resume_attempts');
        return $this->fresh()->resume_attempts;
    }

    public static function startupInterrupted(): array
    {
        return static::where('status', 'running')
            ->where('auto_resume', true)
            ->pluck('session_id')
            ->toArray();
    }

    public static function markRunningAsInterrupted(): void
    {
        static::where('status', 'running')->update([
            'status' => 'interrupted',
            'run_finished_at' => now(),
        ]);
    }
}
