<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Theo dõi token AI đã dùng.
 */
class TokenUsage extends Model
{
    public $timestamps = false;

    protected $table = 'token_usage';

    protected $fillable = [
        'session_id',
        'project_id',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'provider',
        'created_at',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cost_usd' => 'float',
        'created_at' => 'datetime',
    ];

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sessionId' => $this->session_id,
            'projectId' => $this->project_id,
            'inputTokens' => (int) $this->input_tokens,
            'outputTokens' => (int) $this->output_tokens,
            'costUsd' => (float) $this->cost_usd,
            'provider' => $this->provider,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }

    public static function tokensByProject(): array
    {
        return static::select('project_id')
            ->selectRaw('SUM(input_tokens + output_tokens) as tokens')
            ->selectRaw('SUM(cost_usd) as cost_usd')
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id')
            ->map(fn ($r) => [
                'tokens' => (int) $r->tokens,
                'costUsd' => (float) $r->cost_usd,
            ])
            ->toArray();
    }

    public static function totals(): array
    {
        $row = static::selectRaw('
            COALESCE(SUM(input_tokens), 0) as tokens_in,
            COALESCE(SUM(output_tokens), 0) as tokens_out,
            COALESCE(SUM(input_tokens + output_tokens), 0) as tokens,
            COALESCE(SUM(cost_usd), 0) as cost_usd
        ')->first();

        return [
            'tokens' => (int) $row->tokens,
            'costUsd' => (float) $row->cost_usd,
            'tokensIn' => (int) $row->tokens_in,
            'tokensOut' => (int) $row->tokens_out,
        ];
    }

    public static function timeline(int $days, string $scope = 'all'): array
    {
        $since = now()->subDays($days)->toDateTimeString();

        $rows = static::select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("COALESCE(provider, 'claude') as provider"),
                DB::raw('SUM(input_tokens) as tokens_in'),
                DB::raw('SUM(output_tokens) as tokens_out'),
                DB::raw('SUM(input_tokens + output_tokens) as tokens'),
                DB::raw('SUM(cost_usd) as cost_usd')
            )
            ->where('created_at', '>=', $since)
            ->groupBy('date', 'provider')
            ->orderBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $r) {
            $date = $r->date;
            if (!isset($byDate[$date])) {
                $byDate[$date] = [
                    'date' => $date,
                    'tokens' => 0,
                    'tokensIn' => 0,
                    'tokensOut' => 0,
                    'costUsd' => 0,
                    'byProvider' => [],
                ];
            }
            $byDate[$date]['tokens'] += (int) $r->tokens;
            $byDate[$date]['tokensIn'] += (int) $r->tokens_in;
            $byDate[$date]['tokensOut'] += (int) $r->tokens_out;
            $byDate[$date]['costUsd'] += (float) $r->cost_usd;
            $byDate[$date]['byProvider'][$r->provider] =
                ($byDate[$date]['byProvider'][$r->provider] ?? 0) + (int) $r->tokens;
        }

        return array_values($byDate);
    }
}
