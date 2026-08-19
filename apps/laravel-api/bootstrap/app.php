<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // AIEV API routes - hỗ trợ cả /api và /api/v1 cho backward compatibility với Web UI
            \Illuminate\Support\Facades\Route::prefix('api/v1')
                ->middleware('api')
                ->group(base_path('routes/api_v1.php'));

            \Illuminate\Support\Facades\Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Đăng ký alias cho AIEV auth middleware
        $middleware->alias([
            'aiev.auth' => \App\Http\Middleware\AievTokenAuth::class,
        ]);

        // CORS cho web UI :6868
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(function () {
            $request = request();
            if (!$request) return [];

            return [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'input' => $request->except(['password', 'password_confirmation', 'token', 'apiKey', 'api_key']),
            ];
        });

        $exceptions->reportable(function (\Throwable $e) {
            $request = request();
            \Illuminate\Support\Facades\Log::error(sprintf(
                '[%s] %s in %s:%d (URL: %s %s)',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $request ? $request->method() : 'CLI',
                $request ? $request->fullUrl() : 'N/A'
            ), [
                'exception' => $e,
            ]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
