<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AIEV Render Job - quản lý hàng đợi render video.
 *
 * Port từ apps/server/src/db.ts (JobRow interface).
 */
class AievJob extends Model
{
    protected $table = 'aiev_jobs';

    public $incrementing = false;
    protected $keyType = 'string';

    public const ALLOWED_TYPES = [
        'scene-draft',
        'scene-final',
        'assemble-draft',
        'assemble-final',
        'image-gen',
        'auto-cut',
        'auto-trim',
    ];

    public const ALL_TYPES = [
        'scene-draft',
        'scene-final',
        'assemble-draft',
        'assemble-final',
        'image-gen',
        'auto-cut',
        'auto-trim',
        'text-to-video',
        'translate-video',
    ];

    public const STATUSES = ['queued', 'running', 'done', 'failed', 'canceled'];

    protected $fillable = [
        'id',
        'project_id',
        'type',
        'scene_id',
        'status',
        'progress',
        'step',
        'output_path',
        'log',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected $hidden = ['log'];

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->project_id,
            'type' => $this->type,
            'sceneId' => $this->scene_id,
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'step' => $this->step,
            'outputPath' => $this->output_path,
            'createdAt' => $this->created_at?->toISOString(),
            'startedAt' => $this->started_at?->toISOString(),
            'finishedAt' => $this->finished_at?->toISOString(),
        ];
    }

    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['running', 'queued']);
    }

    public static function hasActiveForProject(string $projectId): bool
    {
        return static::active()->forProject($projectId)->exists();
    }

    public static function countQueued(): int
    {
        return static::queued()->count();
    }

    public static function hasDoneAssembleDraft(string $projectId): bool
    {
        return static::where('project_id', $projectId)
            ->where('type', 'assemble-draft')
            ->where('status', 'done')
            ->exists();
    }

    public static function countDoneForProject(string $projectId): int
    {
        return static::where('project_id', $projectId)
            ->where('status', 'done')
            ->count();
    }

    public static function failStaleRunning(): void
    {
        static::running()->update([
            'status' => 'failed',
            'step' => 'Server khởi động lại khi job đang chạy',
            'finished_at' => now(),
        ]);

        static::queued()->update([
            'status' => 'failed',
            'step' => 'Server khởi động lại khi job còn trong hàng đợi',
            'finished_at' => now(),
        ]);
    }
}
