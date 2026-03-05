<?php

use App\Http\Controllers\Api\V1\AgentBackupSettingsController;
use App\Http\Controllers\Api\V1\AgentFeatureSettingsController;
use App\Http\Controllers\Api\V1\AgentJobController;
use App\Http\Controllers\Api\V1\AgentRunController;
use App\Http\Controllers\Api\V1\ChatActionController;
use App\Http\Controllers\Api\V1\ChatSessionController;
use App\Http\Controllers\Api\V1\ComplianceController;
use App\Http\Controllers\Api\V1\CredentialsController;
use App\Http\Controllers\Api\V1\DelegateeProfileController;
use App\Http\Controllers\Api\V1\DelegationGraphController;
use App\Http\Controllers\Api\V1\DelegationTaskController;
use App\Http\Controllers\Api\V1\DeploymentCountingController;
use App\Http\Controllers\Api\V1\Docs\DocsCoverageController;
use App\Http\Controllers\Api\V1\Docs\DocsFragmentController;
use App\Http\Controllers\Api\V1\Docs\DocsSearchController;
use App\Http\Controllers\Api\V1\InterrogationSessionController;
use App\Http\Controllers\Api\V1\InterrogationSettingsController;
use App\Http\Controllers\Api\V1\InterrogationTaskProviderController;
use App\Http\Controllers\Api\V1\InterrogationTechStackController;
use App\Http\Controllers\Api\V1\Memory\MemoryCoreBlockController;
use App\Http\Controllers\Api\V1\Memory\MemoryDiagnosticsController;
use App\Http\Controllers\Api\V1\Memory\MemoryRetrievalController;
use App\Http\Controllers\Api\V1\Memory\MemorySettingsController;
use App\Http\Controllers\Api\V1\Memory\MemoryWorkingController;
use App\Http\Controllers\Api\V1\Messenger\MessengerHealthController;
use App\Http\Controllers\Api\V1\Messenger\MessengerMetricsController;
use App\Http\Controllers\Api\V1\Messenger\WebhookController;
use App\Http\Controllers\Api\V1\MessengerConnectorController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Org;
use App\Http\Controllers\Api\V1\ProjectionReplayBuildController;
use App\Http\Controllers\Api\V1\RepoAnalysisSessionController;
use App\Http\Controllers\Api\V1\Runtime\RuntimeSessionController;
use App\Http\Controllers\Api\V1\Runtime\RuntimeToolCallController;
use App\Http\Controllers\Api\V1\SystemDirectoryPickerController;
use App\Http\Controllers\Api\V1\WorkflowCostController;
use App\Http\Controllers\Api\V1\WorkflowEscalationController;
use App\Http\Controllers\Api\V1\WorkflowGateTransitionController;
use App\Http\Controllers\Api\V1\WorkflowGovernanceController;
use App\Http\Controllers\Api\V1\WorkflowReliabilityController;
use App\Http\Controllers\Docs\DiagnosticsController;
use App\Http\Controllers\Internal\NlScheduleController;
use App\Http\Middleware\AgentApiVersionHeader;
use App\Http\Middleware\Memory\MemoryEnabled;
use App\Http\Middleware\Messenger\CorrelationId;
use App\Http\Middleware\Messenger\ReplayProtection;
use App\Http\Middleware\Messenger\VerifyWebhookSignature;
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

        Route::post('/n8n/webhook', \App\Http\Controllers\Api\V1\N8nWebhookController::class);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/jobs', [AgentJobController::class, 'index']);
            Route::get('/jobs/{id}', [AgentJobController::class, 'show']);
            Route::get('/jobs/by-workflow/{workflowKey}', [AgentJobController::class, 'showByWorkflowKey']);
            Route::post('/jobs', [AgentJobController::class, 'store'])->middleware('throttle:agent-mutations');
            Route::put('/jobs/{id}', [AgentJobController::class, 'update'])->middleware('throttle:agent-mutations');
            Route::post('/jobs/{id}/toggle', [AgentJobController::class, 'toggle'])->middleware('throttle:agent-mutations');
            Route::delete('/jobs/{id}', [AgentJobController::class, 'destroy'])->middleware('throttle:agent-mutations');
            Route::post('/jobs/{id}/restore', [AgentJobController::class, 'restore'])->middleware('throttle:agent-mutations');
            Route::post('/jobs/{id}/run-now', [AgentJobController::class, 'runNow'])->middleware('throttle:agent-mutations');
            Route::get('/jobs/{id}/runs', [AgentJobController::class, 'runs']);
            Route::get('/workflows/{workflowKey}/reliability', [WorkflowReliabilityController::class, 'show']);
            Route::get('/workflows/{workflowKey}/health', [WorkflowReliabilityController::class, 'show']);
            Route::get('/workflows/{workflowKey}/cost', [WorkflowCostController::class, 'show']);
            Route::get('/workflows/{workflowKey}/escalations', [WorkflowEscalationController::class, 'index']);
            Route::get('/workflows/{workflowKey}/gate-transitions', [WorkflowGateTransitionController::class, 'index']);
            Route::post('/workflows/{workflowKey}/pause', [WorkflowGovernanceController::class, 'pause'])
                ->middleware('throttle:agent-mutations');
            Route::post('/workflows/{workflowKey}/resume', [WorkflowGovernanceController::class, 'resume'])
                ->middleware('throttle:agent-mutations');
            Route::get('/deployments/counting', [DeploymentCountingController::class, 'index']);
            Route::get('/telemetry/replay/active-build', [ProjectionReplayBuildController::class, 'activeBuild']);
            Route::get('/telemetry/replay/builds/{buildId}', [ProjectionReplayBuildController::class, 'show']);
            Route::post('/telemetry/replay/builds', [ProjectionReplayBuildController::class, 'store'])
                ->middleware('throttle:agent-mutations');
            Route::post('/telemetry/replay/builds/{buildId}/activate', [ProjectionReplayBuildController::class, 'activate'])
                ->middleware('throttle:agent-mutations');

            Route::get('/runs', [AgentRunController::class, 'index']);
            Route::get('/runs/{id}', [AgentRunController::class, 'show']);
            Route::get('/runs/{id}/events', [AgentRunController::class, 'events']);
            Route::post('/runs/{id}/stop', [AgentRunController::class, 'stop'])->middleware('throttle:agent-mutations');
            Route::post('/runs/{id}/retry', [AgentRunController::class, 'retry'])->middleware('throttle:agent-mutations');
            Route::post('/runs/{id}/confirm-lesson', [AgentRunController::class, 'confirmSuggestedLesson'])->middleware('throttle:agent-mutations');

            Route::get('/dashboard/metrics', [AgentRunController::class, 'dashboardMetrics']);
            Route::get('/health/scheduler', [AgentRunController::class, 'schedulerHealth']);
            Route::get('/health/messenger', [MessengerHealthController::class, 'index']);
            Route::post('/system/directory-picker', [SystemDirectoryPickerController::class, 'store'])
                ->middleware('throttle:agent-mutations');

            Route::get('/messenger/metrics', [MessengerMetricsController::class, 'index']);
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:agent-mutations');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:agent-mutations');
            Route::delete('/notifications', [NotificationController::class, 'clearAll'])->middleware('throttle:agent-mutations');
            Route::get('/backups/settings', [AgentBackupSettingsController::class, 'show']);
            Route::put('/backups/settings', [AgentBackupSettingsController::class, 'update'])->middleware('throttle:agent-mutations');
            Route::post('/backups/run-now', [AgentBackupSettingsController::class, 'runNow'])->middleware('throttle:agent-mutations');
            Route::get('/features/settings', [AgentFeatureSettingsController::class, 'index']);
            Route::put('/features/settings', [AgentFeatureSettingsController::class, 'update'])->middleware('throttle:agent-mutations');

            Route::get('/credentials', [CredentialsController::class, 'index']);
            Route::post('/credentials', [CredentialsController::class, 'store'])->middleware('throttle:agent-mutations');
            Route::delete('/credentials', [CredentialsController::class, 'destroy'])->middleware('throttle:agent-mutations');

            Route::prefix('docs')->group(function (): void {
                Route::get('/search', [DocsSearchController::class, 'index']);
                Route::get('/fragments/{uiKey}', [DocsFragmentController::class, 'show']);
                Route::get('/coverage', [DocsCoverageController::class, 'index'])
                    ->middleware('can:view-docs-coverage');
                Route::get('/diagnostics', DiagnosticsController::class)
                    ->middleware('can:view-docs-diagnostics');
            });

            Route::prefix('compliance')->group(function (): void {
                Route::get('/status', [ComplianceController::class, 'status']);
                Route::get('/metrics', [ComplianceController::class, 'metrics']);
            });

            Route::get('/interrogation/sessions', [InterrogationSessionController::class, 'index']);
            Route::post('/interrogation/sessions', [InterrogationSessionController::class, 'store'])->middleware('throttle:interrogation');
            Route::get('/interrogation/sessions/{id}', [InterrogationSessionController::class, 'show']);
            Route::patch('/interrogation/sessions/{id}', [InterrogationSessionController::class, 'update'])->middleware('throttle:interrogation');
            Route::delete('/interrogation/sessions/{id}', [InterrogationSessionController::class, 'destroy'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/restore', [InterrogationSessionController::class, 'restore'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/pause', [InterrogationSessionController::class, 'pause'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/resume', [InterrogationSessionController::class, 'resume'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/retry', [InterrogationSessionController::class, 'retry'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/restart-from-beginning', [InterrogationSessionController::class, 'restartFromBeginning'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/cleanup-invalid-questions', [InterrogationSessionController::class, 'cleanupInvalidQuestions'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/advance-pre-discovery', [InterrogationSessionController::class, 'advancePreDiscovery'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/start-discovery', [InterrogationSessionController::class, 'startDiscovery'])->middleware('throttle:interrogation');
            Route::get('/interrogation/sessions/{id}/events', [InterrogationSessionController::class, 'events']);
            Route::post('/interrogation/sessions/{id}/answer', [InterrogationSessionController::class, 'submitAnswer'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/answer/edit', [InterrogationSessionController::class, 'editAnswer'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/confirm-summary', [InterrogationSessionController::class, 'confirmSummary'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/revise-summary', [InterrogationSessionController::class, 'reviseSummary'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/continue-interrogation', [InterrogationSessionController::class, 'continueInterrogation'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/generate-plan', [InterrogationSessionController::class, 'generatePlan'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/regenerate-plan', [InterrogationSessionController::class, 'regeneratePlan'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/approve-plan', [InterrogationSessionController::class, 'approvePlan'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/revise-plan', [InterrogationSessionController::class, 'requestRevision'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/generate-build-tasks', [InterrogationSessionController::class, 'generateBuildTasks'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/build-tasks', [InterrogationSessionController::class, 'storeBuildTask'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/build-tasks/reorder', [InterrogationSessionController::class, 'reorderBuildTasks'])->middleware('throttle:interrogation');
            Route::patch('/interrogation/sessions/{id}/build-tasks/{taskId}', [InterrogationSessionController::class, 'updateBuildTask'])->middleware('throttle:interrogation');
            Route::delete('/interrogation/sessions/{id}/build-tasks/{taskId}', [InterrogationSessionController::class, 'destroyBuildTask'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/build-tasks/{taskId}/regenerate', [InterrogationSessionController::class, 'regenerateBuildTask'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/approve-build-tasks', [InterrogationSessionController::class, 'approveBuildTasks'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/start-build', [InterrogationSessionController::class, 'startBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/pause-build', [InterrogationSessionController::class, 'pauseBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/resume-build', [InterrogationSessionController::class, 'resumeBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/build/clarify', [InterrogationSessionController::class, 'clarifyBuild'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/providers/{driver}/oauth/start', [InterrogationTaskProviderController::class, 'startOAuth'])->middleware('throttle:interrogation');
            Route::get('/interrogation/sessions/{id}/providers/{driver}/projects', [InterrogationTaskProviderController::class, 'projects'])->middleware('throttle:interrogation');
            Route::patch('/interrogation/sessions/{id}/providers/{driver}/settings', [InterrogationTaskProviderController::class, 'updateSettings'])->middleware('throttle:interrogation');
            Route::delete('/interrogation/sessions/{id}/providers/{driver}', [InterrogationTaskProviderController::class, 'disconnect'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/tech-stacks', [InterrogationTechStackController::class, 'store'])->middleware('throttle:interrogation');
            Route::delete('/interrogation/sessions/{id}/tech-stacks/{stackId}', [InterrogationTechStackController::class, 'destroy'])->middleware('throttle:interrogation');
            Route::patch('/interrogation/sessions/{id}/annotations', [InterrogationSessionController::class, 'updateAnnotation'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/export-summary', [InterrogationSessionController::class, 'exportSummary'])->middleware('throttle:interrogation');
            Route::post('/interrogation/sessions/{id}/export-plan', [InterrogationSessionController::class, 'exportPlan'])->middleware('throttle:interrogation');

            Route::get('/interrogation/settings', [InterrogationSettingsController::class, 'index']);
            Route::get('/interrogation/settings/{key}', [InterrogationSettingsController::class, 'show']);
            Route::put('/interrogation/settings/{key}', [InterrogationSettingsController::class, 'update'])->middleware('throttle:interrogation');

            Route::get('/code-analysis/sessions', [RepoAnalysisSessionController::class, 'index']);
            Route::post('/code-analysis/sessions', [RepoAnalysisSessionController::class, 'store'])->middleware('throttle:agent-mutations');
            Route::get('/code-analysis/sessions/{id}', [RepoAnalysisSessionController::class, 'show']);
            Route::patch('/code-analysis/sessions/{id}', [RepoAnalysisSessionController::class, 'update'])->middleware('throttle:agent-mutations');
            Route::delete('/code-analysis/sessions/{id}', [RepoAnalysisSessionController::class, 'destroy'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/restore', [RepoAnalysisSessionController::class, 'restore'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/purge', [RepoAnalysisSessionController::class, 'purge'])->middleware('throttle:agent-mutations');

            Route::post('/code-analysis/sessions/{id}/start-snapshot', [RepoAnalysisSessionController::class, 'startSnapshot'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/plan', [RepoAnalysisSessionController::class, 'plan'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/execute', [RepoAnalysisSessionController::class, 'execute'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/retry-task', [RepoAnalysisSessionController::class, 'retryTask'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/validate-coverage', [RepoAnalysisSessionController::class, 'validateCoverage'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/generate-report', [RepoAnalysisSessionController::class, 'generateReport'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/pause', [RepoAnalysisSessionController::class, 'pause'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/resume', [RepoAnalysisSessionController::class, 'resume'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/retry', [RepoAnalysisSessionController::class, 'retry'])->middleware('throttle:agent-mutations');
            Route::post('/code-analysis/sessions/{id}/restart-from-beginning', [RepoAnalysisSessionController::class, 'restartFromBeginning'])->middleware('throttle:agent-mutations');

            Route::get('/code-analysis/sessions/{id}/events', [RepoAnalysisSessionController::class, 'events']);
            Route::get('/code-analysis/sessions/{id}/tasks', [RepoAnalysisSessionController::class, 'tasks']);
            Route::get('/code-analysis/sessions/{id}/artifacts', [RepoAnalysisSessionController::class, 'artifacts']);
            Route::get('/code-analysis/sessions/{id}/reports', [RepoAnalysisSessionController::class, 'reports']);

            // Chat session endpoints
            Route::get('/chat/sessions', [ChatSessionController::class, 'index']);
            Route::get('/chat/sessions/{id}', [ChatSessionController::class, 'show']);
            Route::get('/chat/sessions/{id}/messages', [ChatSessionController::class, 'messages']);
            Route::get('/chat/sessions/{id}/actions', [ChatSessionController::class, 'actions']);

            // Chat action endpoints
            Route::get('/chat/actions/{id}', [ChatActionController::class, 'show']);
            Route::get('/chat/actions/{id}/status', [ChatActionController::class, 'status']);
            Route::post('/chat/actions/{id}/confirm', [ChatActionController::class, 'confirm'])
                ->middleware('throttle:agent-mutations');
            Route::post('/chat/actions/{id}/cancel', [ChatActionController::class, 'cancel'])
                ->middleware('throttle:agent-mutations');

            // Runtime session endpoints
            Route::prefix('chat/runtime')->group(function (): void {
                Route::get('/sessions', [RuntimeSessionController::class, 'index']);
                Route::get('/sessions/{id}', [RuntimeSessionController::class, 'show']);
                Route::post('/sessions/{id}/stop', [RuntimeSessionController::class, 'stop'])
                    ->middleware('throttle:agent-mutations');
                Route::post('/tool-calls/{id}/approve', [RuntimeToolCallController::class, 'approve'])
                    ->middleware('throttle:agent-mutations');
                Route::post('/tool-calls/{id}/deny', [RuntimeToolCallController::class, 'deny'])
                    ->middleware('throttle:agent-mutations');
            });

            // Messenger connector management endpoints
            Route::get('/messenger/connectors/schema', [MessengerConnectorController::class, 'schema']);
            Route::get('/messenger/connectors', [MessengerConnectorController::class, 'index']);
            Route::post('/messenger/connectors', [MessengerConnectorController::class, 'store'])
                ->middleware('throttle:agent-mutations');
            Route::get('/messenger/connectors/{id}', [MessengerConnectorController::class, 'show']);
            Route::put('/messenger/connectors/{id}', [MessengerConnectorController::class, 'update'])
                ->middleware('throttle:agent-mutations');
            Route::delete('/messenger/connectors/{id}', [MessengerConnectorController::class, 'destroy'])
                ->middleware('throttle:agent-mutations');
            Route::post('/messenger/connectors/{id}/test', [MessengerConnectorController::class, 'test'])
                ->middleware('throttle:agent-mutations');
            Route::match(['get', 'put'], '/messenger/connectors/{id}/soul', [MessengerConnectorController::class, 'soul'])
                ->middleware('throttle:agent-mutations');

            // Memory settings routes — always accessible so users can configure
            // provider keys and view capabilities before enabling the full system.
            Route::prefix('memory')->group(function (): void {
                Route::get('/settings', [MemorySettingsController::class, 'index'])
                    ->middleware('throttle:memory-reads');
                Route::put('/settings', [MemorySettingsController::class, 'update'])
                    ->middleware('throttle:memory-writes');
                Route::post('/settings/test-connection', [MemorySettingsController::class, 'testConnection'])
                    ->middleware('throttle:memory-writes');
                Route::get('/settings/capabilities', [MemorySettingsController::class, 'capabilities'])
                    ->middleware('throttle:memory-reads');
            });

            // Memory operational routes — gated by MemoryEnabled middleware
            Route::prefix('memory')->middleware([MemoryEnabled::class])->group(function (): void {
                // Core Blocks
                Route::get('/core-blocks', [MemoryCoreBlockController::class, 'index'])
                    ->middleware('throttle:memory-reads');
                Route::get('/core-blocks/{key}', [MemoryCoreBlockController::class, 'show'])
                    ->middleware('throttle:memory-reads');
                Route::put('/core-blocks/{key}', [MemoryCoreBlockController::class, 'update'])
                    ->middleware('throttle:memory-writes');
                Route::delete('/core-blocks/{key}', [MemoryCoreBlockController::class, 'destroy'])
                    ->middleware('throttle:memory-writes');

                // Retrieval
                Route::post('/retrieve', [MemoryRetrievalController::class, 'retrieve'])
                    ->middleware('throttle:memory-reads');

                // Working Memory
                Route::post('/working/append', [MemoryWorkingController::class, 'append'])
                    ->middleware('throttle:memory-writes');
                Route::get('/working/{runId}', [MemoryWorkingController::class, 'show'])
                    ->middleware('throttle:memory-reads');

                // Diagnostics
                Route::get('/stats', [MemoryDiagnosticsController::class, 'stats'])
                    ->middleware('throttle:memory-reads');
            });

            // Org layer routes (gated by feature flag)
            Route::prefix('org')
                ->middleware(['org'])
                ->group(function (): void {
                    Route::get('/agents', [Org\OrgAgentController::class, 'index']);
                    Route::post('/agents', [Org\OrgAgentController::class, 'store'])
                        ->middleware('throttle:agent-mutations');
                    Route::get('/agents/{id}', [Org\OrgAgentController::class, 'show']);
                    Route::put('/agents/{id}', [Org\OrgAgentController::class, 'update'])
                        ->middleware('throttle:agent-mutations');
                    Route::delete('/agents/{id}', [Org\OrgAgentController::class, 'destroy'])
                        ->middleware('throttle:agent-mutations');
                    Route::post('/agents/{id}/restore', [Org\OrgAgentController::class, 'restore'])
                        ->middleware('throttle:agent-mutations');

                    // Ritual templates
                    Route::get('/rituals', [Org\OrgRitualController::class, 'index']);
                    Route::post('/rituals', [Org\OrgRitualController::class, 'store'])
                        ->middleware('throttle:agent-mutations');
                    Route::get('/rituals/{id}', [Org\OrgRitualController::class, 'show']);
                    Route::put('/rituals/{id}', [Org\OrgRitualController::class, 'update'])
                        ->middleware('throttle:agent-mutations');
                    Route::delete('/rituals/{id}', [Org\OrgRitualController::class, 'destroy'])
                        ->middleware('throttle:agent-mutations');
                    Route::post('/rituals/{id}/restore', [Org\OrgRitualController::class, 'restore'])
                        ->middleware('throttle:agent-mutations');
                    Route::post('/rituals/{id}/run', [Org\OrgRitualController::class, 'run'])
                        ->middleware('throttle:agent-mutations');
                    Route::post('/rituals/{id}/pause', [Org\OrgRitualController::class, 'pause'])
                        ->middleware('throttle:agent-mutations');
                    Route::post('/rituals/{id}/resume', [Org\OrgRitualController::class, 'resume'])
                        ->middleware('throttle:agent-mutations');

                    // Ritual runs
                    Route::get('/ritual-runs', [Org\OrgRitualRunController::class, 'index']);
                    Route::get('/ritual-runs/{id}', [Org\OrgRitualRunController::class, 'show']);
                    Route::post('/ritual-runs/{id}/retry', [Org\OrgRitualRunController::class, 'retry'])
                        ->middleware('throttle:agent-mutations');

                    // Council templates
                    Route::get('/councils', [Org\OrgCouncilController::class, 'index']);
                    Route::post('/councils', [Org\OrgCouncilController::class, 'store'])
                        ->middleware('throttle:agent-mutations');
                    Route::get('/councils/{id}', [Org\OrgCouncilController::class, 'show']);
                    Route::put('/councils/{id}', [Org\OrgCouncilController::class, 'update'])
                        ->middleware('throttle:agent-mutations');
                    Route::delete('/councils/{id}', [Org\OrgCouncilController::class, 'destroy'])
                        ->middleware('throttle:agent-mutations');

                    // Cost governance
                    Route::get('/costs/summary', [Org\OrgCostController::class, 'summary']);

                    // Escalations
                    Route::get('/escalations', [Org\OrgEscalationController::class, 'index']);
                    Route::post('/escalations/{id}/resolve', [Org\OrgEscalationController::class, 'resolve'])
                        ->middleware('throttle:agent-mutations');
                });

            // Delegation routes (gated by feature flag)
            Route::prefix('delegation')->middleware(['delegation'])->group(function (): void {
                // Graphs - CRUD
                Route::get('/graphs', [DelegationGraphController::class, 'index']);
                Route::post('/graphs', [DelegationGraphController::class, 'store'])->middleware('throttle:agent-mutations');
                Route::get('/graphs/{id}', [DelegationGraphController::class, 'show']);
                Route::put('/graphs/{id}', [DelegationGraphController::class, 'update'])->middleware('throttle:agent-mutations');
                Route::delete('/graphs/{id}', [DelegationGraphController::class, 'destroy'])->middleware('throttle:agent-mutations');

                // Graphs - Custom actions
                Route::post('/graphs/{id}/restore', [DelegationGraphController::class, 'restore'])->middleware('throttle:agent-mutations');
                Route::post('/graphs/{id}/validate', [DelegationGraphController::class, 'validate']);
                Route::post('/graphs/{id}/start', [DelegationGraphController::class, 'start'])->middleware('throttle:agent-mutations');
                Route::post('/graphs/{id}/cancel', [DelegationGraphController::class, 'cancel'])->middleware('throttle:agent-mutations');
                Route::post('/graphs/{id}/clone', [DelegationGraphController::class, 'clone'])->middleware('throttle:agent-mutations');
                Route::get('/graphs/{id}/events', [DelegationGraphController::class, 'events']);

                // Tasks
                Route::get('/graphs/{graphId}/tasks', [DelegationTaskController::class, 'index']);
                Route::get('/graphs/{graphId}/tasks/{taskId}', [DelegationTaskController::class, 'show']);
                Route::post('/graphs/{graphId}/tasks/{taskId}/verification/resolve', [DelegationTaskController::class, 'resolveVerification'])->middleware('throttle:agent-mutations');

                // Delegatee Profiles - CRUD
                Route::get('/delegatee-profiles', [DelegateeProfileController::class, 'index']);
                Route::post('/delegatee-profiles', [DelegateeProfileController::class, 'store'])->middleware('throttle:agent-mutations');
                Route::get('/delegatee-profiles/{id}', [DelegateeProfileController::class, 'show']);
                Route::put('/delegatee-profiles/{id}', [DelegateeProfileController::class, 'update'])->middleware('throttle:agent-mutations');
                Route::delete('/delegatee-profiles/{id}', [DelegateeProfileController::class, 'destroy'])->middleware('throttle:agent-mutations');
                Route::post('/delegatee-profiles/{id}/restore', [DelegateeProfileController::class, 'restore'])->middleware('throttle:agent-mutations');
                Route::get('/delegatee-profiles/{id}/trust', [DelegateeProfileController::class, 'trust']);
            });
        });

        // Messenger webhook routes (no auth required, signature verified by middleware)
        Route::middleware([CorrelationId::class, VerifyWebhookSignature::class, ReplayProtection::class])
            ->prefix('connectors')
            ->group(function (): void {
                Route::post('/slack/webhook', [WebhookController::class, 'handleSlack'])
                    ->defaults('provider', 'slack')
                    ->name('agent.api.connectors.slack.webhook');
                Route::post('/telegram/webhook/{accountKey}', [WebhookController::class, 'handleTelegram'])
                    ->defaults('provider', 'telegram')
                    ->name('agent.api.connectors.telegram.webhook');
                Route::post('/discord/webhook', [WebhookController::class, 'handleDiscord'])
                    ->defaults('provider', 'discord')
                    ->name('agent.api.connectors.discord.webhook');
                // WhatsApp requires both GET (verification) and POST (events)
                // GET verification doesn't have signature, so exclude signature middleware
                Route::match(['get', 'post'], '/whatsapp/webhook', [WebhookController::class, 'handleWhatsApp'])
                    ->defaults('provider', 'whatsapp')
                    ->name('agent.api.connectors.whatsapp.webhook');
            });
    });

// Internal API routes for NL Schedule parsing
Route::middleware(['auth:sanctum'])->prefix('internal/api')->group(function (): void {
    Route::post('/schedule/parse', [NlScheduleController::class, 'parse']);
    Route::get('/schedule/parse/{parseAttemptId}', [NlScheduleController::class, 'show']);
});
