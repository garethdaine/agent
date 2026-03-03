---
slug: agent-api-v1-route-inventory
title: Agent API v1 Route Inventory
summary: Complete route inventory for authenticated and webhook endpoints under /agent/api/v1.
section: api
audience: developer
status: published
version: "1.0.0"
tags:
  - api
  - routes
  - inventory
owner: docs-team
route_names:
  - agent.api.connectors.slack.webhook
  - agent.api.connectors.telegram.webhook
  - agent.api.connectors.discord.webhook
  - agent.api.connectors.whatsapp.webhook
setting_keys:
  - api.tokens.default_expiration_days
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-03
---
# Agent API v1 Route Inventory

This inventory is generated from `php artisan route:list --path=agent/api/v1 --json` and tracks the current API surface from code.

## Settings

The API is versioned through `X-Agent-Api-Version: 1.0` and typically protected by `auth:sanctum` for authenticated routes.

## Example

After adding or modifying API routes, run `php artisan docs:generate` to regenerate this inventory before commit.

## Troubleshooting

If an expected endpoint is missing, verify route registration in `routes/api.php` and middleware feature gates.

## Complete Route Table

| Method | URI | Route Name | Controller | Middleware |
| --- | --- | --- | --- | --- |
| POST | `agent/api/v1/backups/run-now` | `-` | `AgentBackupSettingsController@runNow` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PUT | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/actions/{id}` | `-` | `ChatActionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/chat/actions/{id}/cancel` | `-` | `ChatActionController@cancel` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/chat/actions/{id}/confirm` | `-` | `ChatActionController@confirm` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/actions/{id}/status` | `-` | `ChatActionController@status` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/chat/sessions` | `-` | `ChatSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/chat/sessions/{id}` | `-` | `ChatSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/chat/sessions/{id}/actions` | `-` | `ChatSessionController@actions` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/chat/sessions/{id}/messages` | `-` | `ChatSessionController@messages` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions` | `-` | `RepoAnalysisSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions` | `-` | `RepoAnalysisSessionController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PATCH | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| DELETE | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/artifacts` | `-` | `RepoAnalysisSessionController@artifacts` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/events` | `-` | `RepoAnalysisSessionController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/execute` | `-` | `RepoAnalysisSessionController@execute` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/generate-report` | `-` | `RepoAnalysisSessionController@generateReport` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/pause` | `-` | `RepoAnalysisSessionController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/plan` | `-` | `RepoAnalysisSessionController@plan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/reports` | `-` | `RepoAnalysisSessionController@reports` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/restart-from-beginning` | `-` | `RepoAnalysisSessionController@restartFromBeginning` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/restore` | `-` | `RepoAnalysisSessionController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/resume` | `-` | `RepoAnalysisSessionController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/retry` | `-` | `RepoAnalysisSessionController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/retry-task` | `-` | `RepoAnalysisSessionController@retryTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/start-snapshot` | `-` | `RepoAnalysisSessionController@startSnapshot` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/tasks` | `-` | `RepoAnalysisSessionController@tasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/validate-coverage` | `-` | `RepoAnalysisSessionController@validateCoverage` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/compliance/metrics` | `-` | `ComplianceController@metrics` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/compliance/status` | `-` | `ComplianceController@status` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/connectors/discord/webhook` | `agent.api.connectors.discord.webhook` | `WebhookController@handleDiscord` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| POST | `agent/api/v1/connectors/slack/webhook` | `agent.api.connectors.slack.webhook` | `WebhookController@handleSlack` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| POST | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `agent.api.connectors.telegram.webhook` | `WebhookController@handleTelegram` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| GET,POST | `agent/api/v1/connectors/whatsapp/webhook` | `agent.api.connectors.whatsapp.webhook` | `WebhookController@handleWhatsApp` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| GET | `agent/api/v1/dashboard/metrics` | `-` | `AgentRunController@dashboardMetrics` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| POST | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| PUT | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| DELETE | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/delegatee-profiles/{id}/restore` | `-` | `DelegateeProfileController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}/trust` | `-` | `DelegateeProfileController@trust` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| GET | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| POST | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks` | `-` | `DelegationTaskController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}` | `-` | `DelegationTaskController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| POST | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}/verification/resolve` | `-` | `DelegationTaskController@resolveVerification` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| PUT | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| DELETE | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/cancel` | `-` | `DelegationGraphController@cancel` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/clone` | `-` | `DelegationGraphController@clone` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/graphs/{id}/events` | `-` | `DelegationGraphController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| POST | `agent/api/v1/delegation/graphs/{id}/restore` | `-` | `DelegationGraphController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/start` | `-` | `DelegationGraphController@start` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/validate` | `-` | `DelegationGraphController@validate` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, delegation` |
| GET | `agent/api/v1/docs/coverage` | `-` | `DocsCoverageController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, can:view-docs-coverage` |
| GET | `agent/api/v1/docs/diagnostics` | `-` | `DiagnosticsController` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, can:view-docs-diagnostics` |
| GET | `agent/api/v1/docs/fragments/{uiKey}` | `-` | `DocsFragmentController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/docs/search` | `-` | `DocsSearchController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PUT | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/health` | `-` | `Closure` | `api, App\Http\Middleware\AgentApiVersionHeader` |
| GET | `agent/api/v1/health/messenger` | `-` | `MessengerHealthController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/health/scheduler` | `-` | `AgentRunController@schedulerHealth` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/advance-pre-discovery` | `-` | `InterrogationSessionController@advancePreDiscovery` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/annotations` | `-` | `InterrogationSessionController@updateAnnotation` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer` | `-` | `InterrogationSessionController@submitAnswer` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer/edit` | `-` | `InterrogationSessionController@editAnswer` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-build-tasks` | `-` | `InterrogationSessionController@approveBuildTasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-plan` | `-` | `InterrogationSessionController@approvePlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks` | `-` | `InterrogationSessionController@storeBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@updateBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@destroyBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}/regenerate` | `-` | `InterrogationSessionController@regenerateBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build/clarify` | `-` | `InterrogationSessionController@clarifyBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/cleanup-invalid-questions` | `-` | `InterrogationSessionController@cleanupInvalidQuestions` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/confirm-summary` | `-` | `InterrogationSessionController@confirmSummary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/continue-interrogation` | `-` | `InterrogationSessionController@continueInterrogation` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}/events` | `-` | `InterrogationSessionController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-plan` | `-` | `InterrogationSessionController@exportPlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-summary` | `-` | `InterrogationSessionController@exportSummary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-build-tasks` | `-` | `InterrogationSessionController@generateBuildTasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-plan` | `-` | `InterrogationSessionController@generatePlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause` | `-` | `InterrogationSessionController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause-build` | `-` | `InterrogationSessionController@pauseBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}` | `-` | `InterrogationTaskProviderController@disconnect` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/oauth/start` | `-` | `InterrogationTaskProviderController@startOAuth` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/projects` | `-` | `InterrogationTaskProviderController@projects` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/settings` | `-` | `InterrogationTaskProviderController@updateSettings` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/regenerate-plan` | `-` | `InterrogationSessionController@regeneratePlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restart-from-beginning` | `-` | `InterrogationSessionController@restartFromBeginning` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restore` | `-` | `InterrogationSessionController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume` | `-` | `InterrogationSessionController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume-build` | `-` | `InterrogationSessionController@resumeBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/retry` | `-` | `InterrogationSessionController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-plan` | `-` | `InterrogationSessionController@requestRevision` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-summary` | `-` | `InterrogationSessionController@reviseSummary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-build` | `-` | `InterrogationSessionController@startBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-discovery` | `-` | `InterrogationSessionController@startDiscovery` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/tech-stacks` | `-` | `InterrogationTechStackController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/tech-stacks/{stackId}` | `-` | `InterrogationTechStackController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/settings` | `-` | `InterrogationSettingsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PUT | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:interrogation` |
| GET | `agent/api/v1/jobs` | `-` | `AgentJobController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/jobs` | `-` | `AgentJobController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PUT | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| DELETE | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/jobs/{id}/restore` | `-` | `AgentJobController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/jobs/{id}/run-now` | `-` | `AgentJobController@runNow` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/jobs/{id}/runs` | `-` | `AgentJobController@runs` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/jobs/{id}/toggle` | `-` | `AgentJobController@toggle` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/memory/core-blocks` | `-` | `MemoryCoreBlockController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| GET | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| PUT | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-writes` |
| DELETE | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-writes` |
| POST | `agent/api/v1/memory/retrieve` | `-` | `MemoryRetrievalController@retrieve` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| GET | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:memory-reads` |
| PUT | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:memory-writes` |
| GET | `agent/api/v1/memory/settings/capabilities` | `-` | `MemorySettingsController@capabilities` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:memory-reads` |
| POST | `agent/api/v1/memory/settings/test-connection` | `-` | `MemorySettingsController@testConnection` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:memory-writes` |
| GET | `agent/api/v1/memory/stats` | `-` | `MemoryDiagnosticsController@stats` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| POST | `agent/api/v1/memory/working/append` | `-` | `MemoryWorkingController@append` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-writes` |
| GET | `agent/api/v1/memory/working/{runId}` | `-` | `MemoryWorkingController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| GET | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/messenger/connectors/schema` | `-` | `MessengerConnectorController@schema` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| PUT | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| DELETE | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/messenger/connectors/{id}/test` | `-` | `MessengerConnectorController@test` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/messenger/metrics` | `-` | `MessengerMetricsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/notifications` | `-` | `NotificationController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| DELETE | `agent/api/v1/notifications` | `-` | `NotificationController@clearAll` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/notifications/read-all` | `-` | `NotificationController@markAllAsRead` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/notifications/{id}/read` | `-` | `NotificationController@markAsRead` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/org/agents` | `-` | `OrgAgentController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| POST | `agent/api/v1/org/agents` | `-` | `OrgAgentController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| PUT | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| DELETE | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/agents/{id}/restore` | `-` | `OrgAgentController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/costs/summary` | `-` | `OrgCostController@summary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| GET | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| POST | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| PUT | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| DELETE | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/escalations` | `-` | `OrgEscalationController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| POST | `agent/api/v1/org/escalations/{id}/resolve` | `-` | `OrgEscalationController@resolve` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/ritual-runs` | `-` | `OrgRitualRunController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| GET | `agent/api/v1/org/ritual-runs/{id}` | `-` | `OrgRitualRunController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| POST | `agent/api/v1/org/ritual-runs/{id}/retry` | `-` | `OrgRitualRunController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| POST | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org` |
| PUT | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| DELETE | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/pause` | `-` | `OrgRitualController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/restore` | `-` | `OrgRitualController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/resume` | `-` | `OrgRitualController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/run` | `-` | `OrgRitualController@run` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, org, throttle:agent-mutations` |
| GET | `agent/api/v1/runs` | `-` | `AgentRunController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| GET | `agent/api/v1/runs/{id}` | `-` | `AgentRunController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/runs/{id}/confirm-lesson` | `-` | `AgentRunController@confirmSuggestedLesson` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| GET | `agent/api/v1/runs/{id}/events` | `-` | `AgentRunController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum` |
| POST | `agent/api/v1/runs/{id}/retry` | `-` | `AgentRunController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |
| POST | `agent/api/v1/runs/{id}/stop` | `-` | `AgentRunController@stop` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, throttle:agent-mutations` |

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.api.connectors.discord.webhook` | ok | `agent/api/v1/connectors/discord/webhook` | `POST` |
| `agent.api.connectors.slack.webhook` | ok | `agent/api/v1/connectors/slack/webhook` | `POST` |
| `agent.api.connectors.telegram.webhook` | ok | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `POST` |
| `agent.api.connectors.whatsapp.webhook` | ok | `agent/api/v1/connectors/whatsapp/webhook` | `GET,POST` |

### Referenced Settings Keys

- `api.tokens.default_expiration_days`

### Referenced Feature Flags

- `docs_center_enabled`
<!-- AUTO-GENERATED:END -->
