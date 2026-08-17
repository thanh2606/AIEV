<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;

/**
 * System metrics (CPU & GPU) cho HardwareMeter header.
 * Port từ apps/server/src/routes/metrics.ts
 */
class MetricsController extends Controller
{
    public function index(): JsonResponse
    {
        $cpu = $this->getCpuMetrics();
        $gpu = $this->getGpuMetrics();

        return response()->json([
            'cpu' => $cpu,
            'gpu' => $gpu,
            'uptimeSec' => (int) (microtime(true) - LARAVEL_START),
            'memoryMb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'queue' => [
                'running' => AievJob::running()->count(),
                'queued' => AievJob::queued()->count(),
            ],
        ]);
    }

    private function getCpuMetrics(): array
    {
        $threads = 1;
        $model = 'CPU';
        $percent = 0;

        if (file_exists('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor\s*:/m', $cpuinfo, $matches);
            $threads = count($matches[0]) ?: 1;

            if (preg_match('/model name\s*:\s*(.+)$/m', $cpuinfo, $m)) {
                $model = trim($m[1]);
            }
        }

        // Lấy % load average (1 min load)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (isset($load[0])) {
                $percent = (int) min(100, max(0, round(($load[0] / $threads) * 100)));
            }
        }

        return [
            'percent' => $percent,
            'threads' => $threads,
            'model' => $model,
        ];
    }

    private function getGpuMetrics(): array
    {
        $none = [
            'available' => false,
            'name' => null,
            'percent' => null,
            'vramUsedMb' => null,
            'vramTotalMb' => null,
        ];

        // Kiểm tra nvidia-smi
        $nvidiaSmi = trim((string) shell_exec('which nvidia-smi 2>/dev/null'));
        if (empty($nvidiaSmi)) {
            return $none;
        }

        $out = @shell_exec('nvidia-smi --query-gpu=utilization.gpu,memory.used,memory.total,name --format=csv,noheader,nounits 2>/dev/null');
        if (!$out) {
            return $none;
        }

        $lines = explode("\n", trim($out));
        $firstLine = trim($lines[0] ?? '');
        if (empty($firstLine)) {
            return $none;
        }

        $parts = array_map('trim', explode(',', $firstLine));
        if (count($parts) < 4) {
            return $none;
        }

        $util = is_numeric($parts[0]) ? (float) $parts[0] : null;
        $used = is_numeric($parts[1]) ? (float) $parts[1] : null;
        $total = is_numeric($parts[2]) ? (float) $parts[2] : null;
        $name = implode(', ', array_slice($parts, 3));

        return [
            'available' => true,
            'name' => $name ?: null,
            'percent' => $util,
            'vramUsedMb' => $used,
            'vramTotalMb' => $total,
        ];
    }
}
