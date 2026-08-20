<?php

$repoRoot = env('AIEV_REPO_ROOT', dirname(__DIR__, 3));
if (file_exists('/.dockerenv') || is_dir('/app/apps/laravel-api')) {
    if (str_starts_with($repoRoot, '/home/') || !is_dir($repoRoot)) {
        $repoRoot = '/app';
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | AIEV Paths - đường dẫn đến repo root và các thư mục dự án
    |--------------------------------------------------------------------------
    */
    'repo_root' => $repoRoot,

    'paths' => [
        'video_projects' => $repoRoot . '/video-projects',
        'image_projects' => $repoRoot . '/image-projects',
        'auto_cut' => $repoRoot . '/auto-cut',
        'text_to_video' => $repoRoot . '/text-to-video',
        'translate_video' => $repoRoot . '/translate-video',
        'assets' => $repoRoot . '/assets',
        'outputs' => $repoRoot . '/outputs',
        'imports' => $repoRoot . '/imports',
        'engines_remotion' => $repoRoot . '/engines/remotion',
    ],

    /*
    |--------------------------------------------------------------------------
    | Node.js Worker Service
    |--------------------------------------------------------------------------
    */
    'node_worker_url' => env('NODE_WORKER_URL')
        ? ((str_contains(env('NODE_WORKER_URL'), 'node-worker') && gethostbyname('node-worker') === 'node-worker')
            ? str_replace('node-worker', 'localhost', env('NODE_WORKER_URL'))
            : env('NODE_WORKER_URL'))
        : (gethostbyname('node-worker') !== 'node-worker' ? 'http://node-worker:6870' : 'http://localhost:6870'),

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    */
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
    'anthropic_base_url' => env('ANTHROPIC_BASE_URL'),
    'anthropic_model' => env('ANTHROPIC_MODEL'),
    'gemini_api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'soniox_api_key' => env('SONIOX_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Queue / Rendering
    |--------------------------------------------------------------------------
    */
    'queue_concurrency' => (int) env('QUEUE_CONCURRENCY', 2),
    'ai_max_turns' => (int) env('AI_MAX_TURNS', 30),
    'ai_max_attempts' => (int) env('AI_MAX_ATTEMPTS', 12),

    /*
    |--------------------------------------------------------------------------
    | AIEV API Token (auth cho web UI)
    |--------------------------------------------------------------------------
    */
    'api_token' => env('AIEV_API_TOKEN'),
];
