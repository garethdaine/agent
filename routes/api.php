<?php

use App\Http\Controllers\Api\V1\AgentJobController;
use App\Http\Controllers\Api\V1\AgentRunController;
use App\Http\Middleware\AgentApiVersionHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware([AgentApiVersionHeader::class])
    ->prefix('agent/api/v1')
    ->group(function (): void {
        Route::get('/health', fn () => response()->json([
            'ok' => true,
        ]));

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/jobs', [AgentJobController::class, 'index']);
            Route::get('/jobs/{id}', [AgentJobController::class, 'show']);
            Route::post('/jobs', [AgentJobController::class, 'store'])->middleware('throttle:agent-mutations');
            Route::put('/jobs/{id}', [AgentJobController::class, 'update'])->middleware('throttle:agent-mutations');
            Route::post('/jobs/{id}/toggle', [AgentJobController::class, 'toggle'])->middleware('throttle:agent-mutations');
            Route::delete('/jobs/{id}', [AgentJobController::class, 'destroy'])->middleware('throttle:agent-mutations');
            Route::post('/jobs/{id}/restore', [AgentJobController::class, 'restore'])->middleware('throttle:agent-mutations');
            Route::post('/jobs/{id}/run-now', [AgentJobController::class, 'runNow'])->middleware('throttle:agent-mutations');
            Route::get('/jobs/{id}/runs', [AgentJobController::class, 'runs']);

            Route::get('/runs', [AgentRunController::class, 'index']);
            Route::get('/runs/{id}', [AgentRunController::class, 'show']);
            Route::get('/runs/{id}/events', [AgentRunController::class, 'events']);
            Route::post('/runs/{id}/stop', [AgentRunController::class, 'stop'])->middleware('throttle:agent-mutations');

            Route::get('/dashboard/metrics', [AgentRunController::class, 'dashboardMetrics']);
            Route::get('/health/scheduler', [AgentRunController::class, 'schedulerHealth']);
        });
    });
