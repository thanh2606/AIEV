<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrate dữ liệu từ SQLite cũ (apps/server/data/app.sqlite) sang database Laravel.
 *
 * Chạy: php artisan aiev:migrate-data
 */
class MigrateFromSqlite extends Command
{
    protected $signature = 'aiev:migrate-data {--force : Ghi đè dữ liệu đã có}';
    protected $description = 'Import dữ liệu từ SQLite cũ (apps/server/data/app.sqlite) vào database Laravel';

    public function handle(): int
    {
        $sqlitePath = config('aiev.repo_root') . '/apps/server/data/app.sqlite';

        if (!file_exists($sqlitePath)) {
            $this->error("Không tìm thấy SQLite database: {$sqlitePath}");
            return 1;
        }

        $this->info("Đọc dữ liệu từ: {$sqlitePath}");

        try {
            $sqlite = new \PDO("sqlite:{$sqlitePath}");
            $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->error("Không mở được SQLite: {$e->getMessage()}");
            return 1;
        }

        // ---- Jobs ----
        $this->migrateTable($sqlite, 'jobs', 'aiev_jobs', [
            'id' => 'id',
            'projectId' => 'project_id',
            'type' => 'type',
            'sceneId' => 'scene_id',
            'status' => 'status',
            'progress' => 'progress',
            'step' => 'step',
            'outputPath' => 'output_path',
            'log' => 'log',
            'createdAt' => 'created_at',
            'startedAt' => 'started_at',
            'finishedAt' => 'finished_at',
        ]);

        // ---- Chat Sessions ----
        $this->migrateTable($sqlite, 'chat_sessions', 'chat_sessions', [
            'sessionId' => 'session_id',
            'sdkSessionId' => 'sdk_session_id',
            'title' => 'title',
            'projectId' => 'project_id',
            'status' => 'status',
            'model' => 'model',
            'effort' => 'effort',
            'runStartedAt' => 'run_started_at',
            'runFinishedAt' => 'run_finished_at',
            'autoResume' => 'auto_resume',
            'resumeAttempts' => 'resume_attempts',
            'goal' => 'goal',
            'progressMark' => 'progress_mark',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
        ]);

        // ---- Chat Messages ----
        $this->migrateTable($sqlite, 'chat_messages', 'chat_messages', [
            'sessionId' => 'session_id',
            'role' => 'role',
            'kind' => 'kind',
            'content' => 'content',
            'createdAt' => 'created_at',
        ]);

        // ---- Token Usage ----
        $this->migrateTable($sqlite, 'token_usage', 'token_usage', [
            'sessionId' => 'session_id',
            'projectId' => 'project_id',
            'inputTokens' => 'input_tokens',
            'outputTokens' => 'output_tokens',
            'costUsd' => 'cost_usd',
            'provider' => 'provider',
            'createdAt' => 'created_at',
        ]);

        $this->info('✅ Migration hoàn tất!');
        return 0;
    }

    private function migrateTable(\PDO $sqlite, string $srcTable, string $destTable, array $columnMap): void
    {
        $this->info("Importing {$srcTable} → {$destTable}...");

        try {
            $rows = $sqlite->query("SELECT * FROM {$srcTable}")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->warn("  ⚠ Bỏ qua {$srcTable}: {$e->getMessage()}");
            return;
        }

        if (empty($rows)) {
            $this->info("  → 0 rows (bảng trống)");
            return;
        }

        $force = $this->option('force');
        $inserted = 0;
        $skipped = 0;

        foreach (array_chunk($rows, 100) as $chunk) {
            foreach ($chunk as $row) {
                $mapped = [];
                foreach ($columnMap as $src => $dest) {
                    $mapped[$dest] = $row[$src] ?? null;
                }

                // Thêm updated_at nếu bảng yêu cầu nhưng source không có
                if ($destTable === 'aiev_jobs' && !isset($mapped['updated_at'])) {
                    $mapped['updated_at'] = $mapped['created_at'];
                }

                try {
                    if ($force) {
                        DB::table($destTable)->updateOrInsert(
                            array_intersect_key($mapped, array_flip([$this->primaryKeyOf($destTable)])),
                            $mapped,
                        );
                    } else {
                        DB::table($destTable)->insert($mapped);
                    }
                    $inserted++;
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                        $skipped++;
                    } else {
                        $this->warn("  ⚠ Row error: {$e->getMessage()}");
                        $skipped++;
                    }
                }
            }
        }

        $this->info("  → {$inserted} imported, {$skipped} skipped");
    }

    private function primaryKeyOf(string $table): string
    {
        return match ($table) {
            'aiev_jobs' => 'id',
            'chat_sessions' => 'session_id',
            default => 'id',
        };
    }
}
