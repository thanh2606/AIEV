<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AIEV Paths - đường dẫn đến repo root và các thư mục dự án
    |--------------------------------------------------------------------------
    */
    'repo_root' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)),

    'paths' => [
        'video_projects' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/video-projects',
        'image_projects' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/image-projects',
        'auto_cut' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/auto-cut',
        'text_to_video' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/text-to-video',
        'translate_video' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/translate-video',
        'assets' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/assets',
        'outputs' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/outputs',
        'imports' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/imports',
        'engines_remotion' => env('AIEV_REPO_ROOT', dirname(__DIR__, 3)) . '/engines/remotion',
    ],

    /*
    |--------------------------------------------------------------------------
    | Node.js Worker Service
    |--------------------------------------------------------------------------
    */
    'node_worker_url' => env('NODE_WORKER_URL', 'http://localhost:6870'),

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
