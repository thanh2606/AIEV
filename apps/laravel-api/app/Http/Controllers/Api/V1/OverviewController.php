<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AievJob;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard Overview summary data.
 * Port từ apps/server/src/routes/overview.ts
 */
class OverviewController extends Controller
{
    public function index(): JsonResponse
    {
        $runningJobs = AievJob::running()->get();
        $queuedCount = AievJob::queued()->count();
        $recentJobs = AievJob::orderByDesc('created_at')->limit(10)->get();

        $projectController = new ProjectController();
        $recentProjects = array_slice($projectController->index()->getData(true), 0, 10);

        $healthController = new HealthController();
        $health = $healthController->index()->getData(true);

        return response()->json([
            'runningJob' => $runningJobs->first(),
            'runningJobs' => $runningJobs,
            'queuedCount' => $queuedCount,
            'recentJobs' => $recentJobs,
            'recentProjects' => $recentProjects,
            'health' => $health,
        ]);
    }
}
