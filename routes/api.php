<?php

use App\Http\Controllers\Api\V1\AgentJobController;
use App\Http\Controllers\Api\V1\AgentRunController;
use App\Http\Controllers\Api\V1\InterrogationSessionController;
use App\Http\Controllers\Api\V1\InterrogationSettingsController;
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

            Route::get('/interrogation/sessions', [InterrogationSessionController::class, 'index']);
            Route::post('/interrogation/sessions', [InterrogationSessionController::class, 'store'])->middleware('throttle:interrogation');
            Route::get('/interrogation/sessions/{id}', [InterrogationSessionController::class, 'show']);
            Route::delete('/interrogation/sessions/{id}', [InterrogationSessionController::class, 'destroy'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/restore', [InterrogationSessionController::class, 'restore'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/pause', [InterrogationSessionController::class, 'pause'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/resume', [InterrogationSessionController::class, 'resume'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/retry', [InterrogationSessionController::class, 'retry'])->middleware('throttle:interrogation');
            Route::get('/interrogation/sessions/{id}/events', [InterrogationSessionController::class, 'events']);
            Route::post('/interrogation/sessions/{id}/answer', [InterrogationSessionController::class, 'submitAnswer'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/answer/edit', [InterrogationSessionController::class, 'editAnswer'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/confirm-summary', [InterrogationSessionController::class, 'confirmSummary'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/revise-summary', [InterrogationSessionController::class, 'reviseSummary'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/continue-interrogation', [InterrogationSessionController::class, 'continueInterrogation'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/generate-plan', [InterrogationSessionController::class, 'generatePlan'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/approve-plan', [InterrogationSessionController::class, 'approvePlan'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/revise-plan', [InterrogationSessionController::class, 'requestRevision'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/generate-build-tasks', [InterrogationSessionController::class, 'generateBuildTasks'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/start-build', [InterrogationSessionController::class, 'startBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/pause-build', [InterrogationSessionController::class, 'pauseBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/resume-build', [InterrogationSessionController::class, 'resumeBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/build/clarify', [InterrogationSessionController::class, 'clarifyBuild'])->middleware('throttle:interrogation');
            Route::patch('/interrogation/sessions/{id}/annotations', [InterrogationSessionController::class, 'updateAnnotation'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/export-summary', [InterrogationSessionController::class, 'exportSummary'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/export-plan', [InterrogationSessionController::class, 'exportPlan'])->middleware('throttle:interrogation');

            Route::get('/interrogation/settings', [InterrogationSettingsController::class, 'index']);
            Route::get('/interrogation/settings/{key}', [InterrogationSettingsController::class, 'show']);
            Route::put('/interrogation/settings/{key}', [InterrogationSettingsController::class, 'update'])->middleware('throttle:interrogation');
        });
    });
