<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quản lý render jobs.
 * Port từ apps/server/src/routes/jobs.ts
 */
class JobController extends Controller
{
    /** GET /api/v1/jobs */
    public function index(Request $request): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->query('limit', 50)));
        $projectId = $request->query('projectId');

        $query = AievJob::orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($projectId) {
            $query->forProject($projectId);
        }

        return response()->json($query->get());
    }

    /** GET /api/v1/jobs/{id} */
    public function show(string $id): JsonResponse
    {
        $job = AievJob::findOrFail($id);
        return response()->json($job);
    }

    /** POST /api/v1/jobs */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'projectId' => 'required|string',
            'type' => 'required|string|in:' . implode(',', AievJob::ALLOWED_TYPES),
            'sceneId' => 'nullable|string',
        ]);

        $jobId = 'job_' . \Illuminate\Support\Str::random(21);

        $job = AievJob::create([
            'id' => $jobId,
            'project_id' => $request->input('projectId'),
            'type' => $request->input('type'),
            'scene_id' => $request->input('sceneId'),
            'status' => 'queued',
            'progress' => 0,
            'step' => 'Đang chờ trong hàng đợi',
        ]);

        // Dispatch vào Horizon queue
        dispatch(new \App\Jobs\ProcessRenderJob($job->id));

        return response()->json($job, 201);
    }

    /** PATCH /api/v1/jobs/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $job = AievJob::findOrFail($id);

        $allowed = ['status', 'progress', 'step', 'output_path', 'started_at', 'finished_at'];
        $patch = $request->only($allowed);

        $job->update($patch);

        return response()->json($job->fresh());
    }

    /** DELETE /api/v1/jobs/{id}/cancel */
    public function cancel(string $id): JsonResponse
    {
        $job = AievJob::findOrFail($id);

        if (!in_array($job->status, ['queued', 'running'])) {
            return response()->json([
                'error' => ['code' => 'NOT_CANCELABLE', 'message' => 'Job không thể hủy ở trạng thái này'],
            ], 409);
        }

        $job->update([
            'status' => 'canceled',
            'step' => 'Đã hủy',
            'finished_at' => now(),
        ]);

        return response()->json($job->fresh());
    }

    /** GET /api/v1/jobs/{id}/log */
    public function log(string $id): JsonResponse
    {
        $job = AievJob::findOrFail($id);
        return response()->json([
            'jobId' => $job->id,
            'log' => $job->log,
        ]);
    }
}
