<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\StyleController;
use App\Http\Controllers\Api\V1\VideoStyleController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\OverviewController;
use App\Http\Controllers\Api\V1\ProjectReviewController;
use App\Http\Controllers\Api\V1\ProjectClipsController;
use App\Http\Controllers\Api\V1\ProjectQcController;
use App\Http\Controllers\Api\V1\ProjectPublishController;
use App\Http\Controllers\Api\V1\ProjectAutoTrimController;
use App\Http\Controllers\Api\V1\ThumbnailController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\UploadSessionController;
use App\Http\Controllers\Api\V1\AutoCutController;
use App\Http\Controllers\Api\V1\TextToVideoController;
use App\Http\Controllers\Api\V1\TranslateVideoController;
use App\Http\Controllers\Api\V1\IllustrationController;
use App\Http\Controllers\Api\V1\ImageProjectController;
use App\Http\Controllers\Api\V1\TtsController;
use App\Http\Controllers\Api\V1\VoiceController;
use App\Http\Controllers\Api\V1\SfxController;
use App\Http\Controllers\Api\V1\MusicController;
use App\Http\Controllers\Api\V1\BrandLogoController;
use App\Http\Controllers\Api\V1\PromptController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\DoctorController;
use App\Http\Controllers\Api\V1\UpdateController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\RenderSettingsController;
use App\Http\Controllers\Api\V1\EventController;

/*
|--------------------------------------------------------------------------
| AIEV API Routes (v1)
|--------------------------------------------------------------------------
*/

// ---- Public ----
Route::get('/health', [HealthController::class, 'index']);
Route::get('/events', [EventController::class, 'stream']);

// ---- Protected (AIEV Token) ----
Route::middleware('aiev.auth')->group(function () {

    // Projects
    Route::apiResource('projects', ProjectController::class)->parameters(['projects' => 'id']);
    Route::post('projects/{id}/clone', [ProjectController::class, 'clone']);
    Route::get('projects/{id}/junk', [ProjectController::class, 'junk']);
    Route::post('projects/{id}/junk/clean', [ProjectController::class, 'cleanJunk']);
    Route::put('projects/{id}/name', [ProjectController::class, 'updateName']);
    Route::put('projects/{id}/tags', [ProjectController::class, 'updateTags']);
    Route::put('projects/{id}/brief', [ProjectController::class, 'updateBrief']);
    Route::post('projects/{id}/edit', [ProjectController::class, 'edit']);

    Route::get('projects/{id}/review', [ProjectReviewController::class, 'show']);
    Route::post('projects/{id}/review', [ProjectReviewController::class, 'store']);
    Route::get('projects/{id}/clips', [ProjectClipsController::class, 'index']);
    Route::get('projects/{id}/qc', [ProjectQcController::class, 'show']);
    Route::get('projects/{id}/publish', [ProjectPublishController::class, 'show']);
    Route::post('projects/{id}/auto-trim', [ProjectAutoTrimController::class, 'store']);
    Route::post('projects/{id}/thumbnail', [ThumbnailController::class, 'generateProjectThumbnail']);
    Route::post('projects/{id}/scenes/{sceneId}/thumbnail', [ThumbnailController::class, 'generateSceneThumbnail']);
    
    // Project Assets Custom Endpoints
    Route::get('projects/{id}/assets', [AssetController::class, 'projectAssets']);
    Route::put('projects/{id}/assets/{file}/description', [AssetController::class, 'updateDescription'])->where('file', '.*');
    Route::delete('projects/{id}/assets/{file}', [AssetController::class, 'destroyAsset'])->where('file', '.*');
    Route::put('projects/{id}/assets/{file}/grade', [AssetController::class, 'updateGrade'])->where('file', '.*');
    Route::post('projects/{id}/assets/{file}/grade-preview', [AssetController::class, 'gradePreview'])->where('file', '.*');
    Route::post('projects/{id}/assets/{file}/grade-frame', [AssetController::class, 'gradeFrame'])->where('file', '.*');

    // Assets & Upload
    Route::get('assets', [AssetController::class, 'index']);
    Route::post('assets', [AssetController::class, 'upload']);
    Route::get('upload-session/{token}', [UploadSessionController::class, 'show']);

    // System Dashboard & Monitoring
    Route::get('overview', [OverviewController::class, 'index']);
    Route::get('metrics', [MetricsController::class, 'index']);
    Route::get('doctor', [DoctorController::class, 'index']);
    Route::post('doctor/fix', [DoctorController::class, 'fix']);
    Route::get('update/check', [UpdateController::class, 'check']);
    Route::get('update/log', [UpdateController::class, 'log']);
    Route::post('update/apply', [UpdateController::class, 'apply']);
    Route::get('lan-info', [SystemController::class, 'lanInfo']);
    Route::get('tunnel', [SystemController::class, 'tunnel']);
    Route::post('reveal', [SystemController::class, 'reveal']);

    // Settings & Styles & Presets
    Route::get('render-settings', [RenderSettingsController::class, 'show']);
    Route::put('render-settings', [RenderSettingsController::class, 'update']);
    Route::get('grade-presets', [StyleController::class, 'gradePresets']);
    Route::get('styles', [StyleController::class, 'index']);
    Route::get('styles/{id}', [StyleController::class, 'show']);
    Route::get('video-styles', [VideoStyleController::class, 'index']);
    Route::get('providers', [ProviderController::class, 'index']);

    // Jobs
    Route::get('jobs', [JobController::class, 'index']);
    Route::post('jobs', [JobController::class, 'store']);
    Route::get('jobs/{id}', [JobController::class, 'show']);
    Route::patch('jobs/{id}', [JobController::class, 'update']);
    Route::post('jobs/{id}/cancel', [JobController::class, 'cancel']);
    Route::get('jobs/{id}/log', [JobController::class, 'log']);

    // Connections (AI providers)
    Route::get('connections', [ConnectionController::class, 'index']);
    Route::put('connections/{provider}/key', [ConnectionController::class, 'updateKey']);
    Route::post('connections/{provider}/test', [ConnectionController::class, 'test']);

    // Chat (Claude Agent)
    Route::get('chat/sessions', [ChatController::class, 'sessions']);
    Route::get('chat/{sessionId}/messages', [ChatController::class, 'messages']);
    Route::post('chat', [ChatController::class, 'sendMessage']);
    Route::put('chat/{sessionId}/auto-resume', [ChatController::class, 'updateAutoResume']);
    Route::post('chat/{sessionId}/interrupt', [ChatController::class, 'interrupt']);

    // Usage (Token/Cost dashboard)
    Route::get('usage/summary', [UsageController::class, 'summary']);
    Route::get('usage/timeline', [UsageController::class, 'timeline']);
    Route::get('usage/by-project', [UsageController::class, 'byProject']);

    // AI Modules & Media Libraries
    // Auto-cut
    Route::get('auto-cut/sources', [AutoCutController::class, 'sources']);
    Route::get('auto-cut', [AutoCutController::class, 'index']);
    Route::post('auto-cut', [AutoCutController::class, 'store']);
    Route::get('auto-cut/{id}', [AutoCutController::class, 'show']);
    Route::patch('auto-cut/{id}', [AutoCutController::class, 'update']);
    Route::delete('auto-cut/{id}', [AutoCutController::class, 'destroy']);
    Route::post('auto-cut/{id}/plan', [AutoCutController::class, 'plan']);
    Route::post('auto-cut/{id}/cut', [AutoCutController::class, 'cut']);

    // Text to video
    Route::get('text-to-video', [TextToVideoController::class, 'index']);
    Route::post('text-to-video', [TextToVideoController::class, 'store']);
    Route::get('text-to-video/{id}', [TextToVideoController::class, 'show']);
    Route::patch('text-to-video/{id}', [TextToVideoController::class, 'update']);
    Route::delete('text-to-video/{id}', [TextToVideoController::class, 'destroy']);
    Route::post('text-to-video/{id}/extract', [TextToVideoController::class, 'extract']);
    Route::post('text-to-video/{id}/script', [TextToVideoController::class, 'script']);
    Route::post('text-to-video/{id}/build', [TextToVideoController::class, 'build']);

    // Translate video
    Route::get('translate-video/fonts', [TranslateVideoController::class, 'fonts']);
    Route::get('translate-video/stt-providers', [TranslateVideoController::class, 'sttProviders']);
    Route::get('translate-video', [TranslateVideoController::class, 'index']);
    Route::post('translate-video', [TranslateVideoController::class, 'store']);
    Route::get('translate-video/{id}', [TranslateVideoController::class, 'show']);
    Route::patch('translate-video/{id}', [TranslateVideoController::class, 'update']);
    Route::delete('translate-video/{id}', [TranslateVideoController::class, 'destroy']);
    Route::post('translate-video/{id}/source', [TranslateVideoController::class, 'source']);
    Route::post('translate-video/{id}/transcribe', [TranslateVideoController::class, 'transcribe']);
    Route::post('translate-video/{id}/translate', [TranslateVideoController::class, 'translate']);
    Route::post('illustrations', [IllustrationController::class, 'generate']);
    Route::get('images', [ImageProjectController::class, 'index']);
    
    // TTS routes
    Route::get('tts/engines', [TtsController::class, 'engines']);
    Route::get('tts/models', [TtsController::class, 'models']);
    Route::get('tts/voices', [TtsController::class, 'voices']);
    Route::get('tts/languages', [TtsController::class, 'languages']);
    Route::post('tts/preview', [TtsController::class, 'preview']);
    Route::post('tts', [TtsController::class, 'synthesize']);
    
    Route::get('voices', [VoiceController::class, 'index']);
    Route::post('voices', [VoiceController::class, 'store']);
    Route::patch('voices/{id}', [VoiceController::class, 'update']);
    Route::delete('voices/{id}', [VoiceController::class, 'destroy']);
    Route::post('voices/{id}/preview', [VoiceController::class, 'preview']);
    Route::get('sfx', [SfxController::class, 'index']);
    Route::get('music', [MusicController::class, 'index']);
    Route::get('brand-logos', [BrandLogoController::class, 'index']);
    Route::post('brand-logos', [BrandLogoController::class, 'store']);
    Route::get('prompts', [PromptController::class, 'index']);
    Route::get('skills', [SkillController::class, 'index']);
});

// Static Media serving route under /media/{path} and /api/media/{path}
Route::get('media/{path}', [MediaController::class, 'show'])->where('path', '.*');
