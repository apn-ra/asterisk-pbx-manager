<?php

use AsteriskPbxManager\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Asterisk PBX Manager Health Check Routes
|--------------------------------------------------------------------------
|
| These routes provide health check endpoints for monitoring the Asterisk
| PBX Manager service. They are designed to work with load balancers,
| monitoring systems, and container orchestration platforms.
|
*/

// Health Check Endpoints
Route::group(['prefix' => 'asterisk', 'middleware' => ['web']], function () {
    // Comprehensive health check with detailed information
    Route::get('/health', [HealthCheckController::class, 'health'])
        ->name('asterisk.health');

    // Simple health check (lightweight)
    Route::get('/healthz', [HealthCheckController::class, 'healthz'])
        ->name('asterisk.healthz');

    // Liveness probe (Kubernetes-style)
    Route::get('/live', [HealthCheckController::class, 'live'])
        ->name('asterisk.live');

    // Readiness probe (Kubernetes-style)
    Route::get('/ready', [HealthCheckController::class, 'ready'])
        ->name('asterisk.ready');

    // System status and metrics
    Route::get('/status', [HealthCheckController::class, 'status'])
        ->name('asterisk.status');

    // Clear health check cache (admin endpoint)
    Route::post('/health/cache/clear', [HealthCheckController::class, 'clearCache'])
        ->name('asterisk.health.cache.clear');
});

// Alternative API-style routes (without prefix for simpler integration)
Route::group(['prefix' => 'api/health', 'middleware' => ['api']], function () {
    // RESTful health endpoints
    Route::get('/', [HealthCheckController::class, 'health'])
        ->name('api.asterisk.health');

    Route::get('/simple', [HealthCheckController::class, 'healthz'])
        ->name('api.asterisk.healthz');

    Route::get('/status', [HealthCheckController::class, 'status'])
        ->name('api.asterisk.status');

    // Cache management
    Route::delete('/cache', [HealthCheckController::class, 'clearCache'])
        ->name('api.asterisk.health.cache.clear');
});

// Root-level endpoints for load balancers and simple monitoring
Route::group(['middleware' => ['web']], function () {
    // Standard health check endpoint
    Route::get('/health-check', [HealthCheckController::class, 'healthz'])
        ->name('health-check');

    // Simple ping endpoint
    Route::get('/ping', [HealthCheckController::class, 'live'])
        ->name('ping');
});
