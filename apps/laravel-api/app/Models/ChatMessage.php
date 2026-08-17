<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tin nhắn trong phiên chat.
 */
class ChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'role',
        'kind',
        'content',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sessionId' => $this->session_id,
            'role' => $this->role,
            'kind' => $this->kind,
            'content' => $this->content,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id', 'session_id');
    }
}
