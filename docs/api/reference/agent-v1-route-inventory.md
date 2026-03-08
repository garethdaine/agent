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
reviewed_at: 2026-03-08
---

## Settings

The API is versioned through `X-Agent-Api-Version: 1.0` and typically protected by `auth:sanctum` for authenticated routes.

## Example

After adding or modifying API routes, run `php artisan docs:generate` to regenerate this inventory before commit.

## Troubleshooting

If an expected endpoint is missing, verify route registration in `routes/api.php` and middleware feature gates.

## Complete Route Table

| Method | URI | Route Name | Controller | Middleware |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/audit-log` | `-` | `AuditLogController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/backups/run-now` | `-` | `AgentBackupSettingsController@runNow` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/actions/{id}` | `-` | `ChatActionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/chat/actions/{id}/cancel` | `-` | `ChatActionController@cancel` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/chat/actions/{id}/confirm` | `-` | `ChatActionController@confirm` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/actions/{id}/status` | `-` | `ChatActionController@status` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/chat/commands` | `-` | `ChatSessionController@commands` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/chat/connectors` | `-` | `ChatSessionController@connectors` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/chat/runtime/sessions` | `-` | `RuntimeSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/chat/runtime/sessions/{id}` | `-` | `RuntimeSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/chat/runtime/sessions/{id}/stop` | `-` | `RuntimeSessionController@stop` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/chat/runtime/tool-calls/{id}/approve` | `-` | `RuntimeToolCallController@approve` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/chat/runtime/tool-calls/{id}/deny` | `-` | `RuntimeToolCallController@deny` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/sessions` | `-` | `ChatSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/chat/sessions` | `-` | `ChatSessionController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/sessions/{id}` | `-` | `ChatSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/chat/sessions/{id}/actions` | `-` | `ChatSessionController@actions` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/chat/sessions/{id}/archive` | `-` | `ChatSessionController@archive` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/chat/sessions/{id}/messages` | `-` | `ChatSessionController@messages` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/chat/sessions/{id}/send` | `-` | `ChatSessionController@send` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions` | `-` | `RepoAnalysisSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/code-analysis/sessions` | `-` | `RepoAnalysisSessionController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PATCH | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| DELETE | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/artifacts` | `-` | `RepoAnalysisSessionController@artifacts` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/events` | `-` | `RepoAnalysisSessionController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/execute` | `-` | `RepoAnalysisSessionController@execute` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/generate-report` | `-` | `RepoAnalysisSessionController@generateReport` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/pause` | `-` | `RepoAnalysisSessionController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/plan` | `-` | `RepoAnalysisSessionController@plan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/purge` | `-` | `RepoAnalysisSessionController@purge` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/reports` | `-` | `RepoAnalysisSessionController@reports` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/restart-from-beginning` | `-` | `RepoAnalysisSessionController@restartFromBeginning` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/restore` | `-` | `RepoAnalysisSessionController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/resume` | `-` | `RepoAnalysisSessionController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/retry` | `-` | `RepoAnalysisSessionController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/retry-task` | `-` | `RepoAnalysisSessionController@retryTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/start-snapshot` | `-` | `RepoAnalysisSessionController@startSnapshot` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/tasks` | `-` | `RepoAnalysisSessionController@tasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/validate-coverage` | `-` | `RepoAnalysisSessionController@validateCoverage` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/compliance/metrics` | `-` | `ComplianceController@metrics` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/compliance/status` | `-` | `ComplianceController@status` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/configuration` | `-` | `ConfigurationController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/configuration` | `-` | `ConfigurationController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/connectors` | `-` | `ConnectorLibraryController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/connectors/callback` | `connectors.oauth.callback` | `ConnectorOAuthCallbackController` | `api, App\Http\Middleware\AgentApiVersionHeader` |
| POST | `agent/api/v1/connectors/discord/webhook` | `agent.api.connectors.discord.webhook` | `WebhookController@handleDiscord` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| POST | `agent/api/v1/connectors/slack/webhook` | `agent.api.connectors.slack.webhook` | `WebhookController@handleSlack` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| POST | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `agent.api.connectors.telegram.webhook` | `WebhookController@handleTelegram` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| GET,POST | `agent/api/v1/connectors/whatsapp/webhook` | `agent.api.connectors.whatsapp.webhook` | `WebhookController@handleWhatsApp` | `api, App\Http\Middleware\AgentApiVersionHeader, App\Http\Middleware\Messenger\CorrelationId, App\Http\Middleware\Messenger\VerifyWebhookSignature, App\Http\Middleware\Messenger\ReplayProtection` |
| GET | `agent/api/v1/connectors/{id}` | `-` | `ConnectorLibraryController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/connectors/{id}/actions` | `-` | `ConnectorLibraryController@actions` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/connectors/{id}/connect` | `-` | `ConnectorConnectionController@connect` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| DELETE | `agent/api/v1/connectors/{id}/disconnect` | `-` | `ConnectorConnectionController@disconnect` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/connectors/{id}/health` | `-` | `ConnectorConnectionController@health` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/connectors/{id}/telemetry` | `-` | `ConnectorTelemetryController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/connectors/{id}/test` | `-` | `ConnectorConnectionController@test` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/credentials` | `-` | `CredentialsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/credentials` | `-` | `CredentialsController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| DELETE | `agent/api/v1/credentials` | `-` | `CredentialsController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/dashboard/metrics` | `-` | `AgentRunController@dashboardMetrics` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/debug` | `-` | `DebugPanelController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/delegation/capabilities` | `-` | `DelegateeProfileController@capabilities` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| GET | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| POST | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| PUT | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| DELETE | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/delegatee-profiles/{id}/restore` | `-` | `DelegateeProfileController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}/trust` | `-` | `DelegateeProfileController@trust` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| GET | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| POST | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks` | `-` | `DelegationTaskController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}` | `-` | `DelegationTaskController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| POST | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}/verification/resolve` | `-` | `DelegationTaskController@resolveVerification` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| PUT | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| DELETE | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/cancel` | `-` | `DelegationGraphController@cancel` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/clone` | `-` | `DelegationGraphController@clone` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| GET | `agent/api/v1/delegation/graphs/{id}/events` | `-` | `DelegationGraphController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| POST | `agent/api/v1/delegation/graphs/{id}/restore` | `-` | `DelegationGraphController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/start` | `-` | `DelegationGraphController@start` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation, throttle:agent-mutations` |
| POST | `agent/api/v1/delegation/graphs/{id}/validate` | `-` | `DelegationGraphController@validate` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, delegation` |
| GET | `agent/api/v1/deployments/counting` | `-` | `DeploymentCountingController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/diagnostics` | `-` | `DiagnosticsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/docs/coverage` | `-` | `DocsCoverageController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, can:view-docs-coverage` |
| GET | `agent/api/v1/docs/diagnostics` | `-` | `DiagnosticsController` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, can:view-docs-diagnostics` |
| GET | `agent/api/v1/docs/fragments/{uiKey}` | `-` | `DocsFragmentController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/docs/search` | `-` | `DocsSearchController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/health` | `-` | `Closure` | `api, App\Http\Middleware\AgentApiVersionHeader` |
| GET | `agent/api/v1/health/messenger` | `-` | `MessengerHealthController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/health/scheduler` | `-` | `AgentRunController@schedulerHealth` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/interrogation/runner-models` | `-` | `RunnerModelsController` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/advance-pre-discovery` | `-` | `InterrogationSessionController@advancePreDiscovery` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/annotations` | `-` | `InterrogationSessionController@updateAnnotation` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer` | `-` | `InterrogationSessionController@submitAnswer` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer/edit` | `-` | `InterrogationSessionController@editAnswer` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-build-tasks` | `-` | `InterrogationSessionController@approveBuildTasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-plan` | `-` | `InterrogationSessionController@approvePlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks` | `-` | `InterrogationSessionController@storeBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/reorder` | `-` | `InterrogationSessionController@reorderBuildTasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@updateBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@destroyBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}/regenerate` | `-` | `InterrogationSessionController@regenerateBuildTask` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build/clarify` | `-` | `InterrogationSessionController@clarifyBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/cleanup-invalid-questions` | `-` | `InterrogationSessionController@cleanupInvalidQuestions` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/confirm-summary` | `-` | `InterrogationSessionController@confirmSummary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/continue-interrogation` | `-` | `InterrogationSessionController@continueInterrogation` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}/events` | `-` | `InterrogationSessionController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-plan` | `-` | `InterrogationSessionController@exportPlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-summary` | `-` | `InterrogationSessionController@exportSummary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-build-tasks` | `-` | `InterrogationSessionController@generateBuildTasks` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-plan` | `-` | `InterrogationSessionController@generatePlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause` | `-` | `InterrogationSessionController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause-build` | `-` | `InterrogationSessionController@pauseBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}` | `-` | `InterrogationTaskProviderController@disconnect` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/oauth/start` | `-` | `InterrogationTaskProviderController@startOAuth` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/projects` | `-` | `InterrogationTaskProviderController@projects` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/settings` | `-` | `InterrogationTaskProviderController@updateSettings` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/regenerate-plan` | `-` | `InterrogationSessionController@regeneratePlan` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restart-from-beginning` | `-` | `InterrogationSessionController@restartFromBeginning` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restore` | `-` | `InterrogationSessionController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume` | `-` | `InterrogationSessionController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume-build` | `-` | `InterrogationSessionController@resumeBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/retry` | `-` | `InterrogationSessionController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-plan` | `-` | `InterrogationSessionController@requestRevision` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-summary` | `-` | `InterrogationSessionController@reviseSummary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-build` | `-` | `InterrogationSessionController@startBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-discovery` | `-` | `InterrogationSessionController@startDiscovery` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/tech-stacks` | `-` | `InterrogationTechStackController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/tech-stacks/{stackId}` | `-` | `InterrogationTechStackController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| GET | `agent/api/v1/interrogation/settings` | `-` | `InterrogationSettingsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:interrogation` |
| GET | `agent/api/v1/jobs` | `-` | `AgentJobController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/jobs` | `-` | `AgentJobController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/jobs/by-workflow/{workflowKey}` | `-` | `AgentJobController@showByWorkflowKey` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| DELETE | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/jobs/{id}/restore` | `-` | `AgentJobController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/jobs/{id}/run-now` | `-` | `AgentJobController@runNow` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/jobs/{id}/runs` | `-` | `AgentJobController@runs` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/jobs/{id}/toggle` | `-` | `AgentJobController@toggle` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/logs` | `-` | `LogTailController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/logs/export` | `-` | `LogTailController@export` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/memory/core-blocks` | `-` | `MemoryCoreBlockController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| GET | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| PUT | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-writes` |
| DELETE | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-writes` |
| GET | `agent/api/v1/memory/models` | `-` | `MemoryModelsController` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:memory-reads` |
| POST | `agent/api/v1/memory/retrieve` | `-` | `MemoryRetrievalController@retrieve` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| GET | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:memory-reads` |
| PUT | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:memory-writes` |
| GET | `agent/api/v1/memory/settings/capabilities` | `-` | `MemorySettingsController@capabilities` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:memory-reads` |
| POST | `agent/api/v1/memory/settings/test-connection` | `-` | `MemorySettingsController@testConnection` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:memory-writes` |
| GET | `agent/api/v1/memory/stats` | `-` | `MemoryDiagnosticsController@stats` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| POST | `agent/api/v1/memory/working/append` | `-` | `MemoryWorkingController@append` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-writes` |
| GET | `agent/api/v1/memory/working/{runId}` | `-` | `MemoryWorkingController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, App\Http\Middleware\Memory\MemoryEnabled, throttle:memory-reads` |
| GET | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/messenger/connectors/schema` | `-` | `MessengerConnectorController@schema` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| DELETE | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/messenger/connectors/{id}/policy` | `-` | `ConnectorPolicyController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PUT | `agent/api/v1/messenger/connectors/{id}/policy` | `-` | `ConnectorPolicyController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET,PUT | `agent/api/v1/messenger/connectors/{id}/soul` | `-` | `MessengerConnectorController@soul` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/messenger/connectors/{id}/test` | `-` | `MessengerConnectorController@test` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/messenger/metrics` | `-` | `MessengerMetricsController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/messenger/pairings` | `-` | `PairingController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/messenger/pairings/{id}/approve` | `-` | `PairingController@approve` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/messenger/pairings/{id}/revoke` | `-` | `PairingController@revoke` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/n8n/webhook` | `-` | `N8nWebhookController` | `api, App\Http\Middleware\AgentApiVersionHeader` |
| GET | `agent/api/v1/notifications` | `-` | `NotificationController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| DELETE | `agent/api/v1/notifications` | `-` | `NotificationController@clearAll` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/notifications/read-all` | `-` | `NotificationController@markAllAsRead` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/notifications/{id}/read` | `-` | `NotificationController@markAsRead` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/office/state` | `-` | `OfficeStateController` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/org/agents` | `-` | `OrgAgentController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| POST | `agent/api/v1/org/agents` | `-` | `OrgAgentController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| PUT | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| DELETE | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/agents/{id}/restore` | `-` | `OrgAgentController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/costs/summary` | `-` | `OrgCostController@summary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| GET | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| POST | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| PUT | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| DELETE | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/escalations` | `-` | `OrgEscalationController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| POST | `agent/api/v1/org/escalations/{id}/resolve` | `-` | `OrgEscalationController@resolve` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/nl-apply` | `-` | `NlOrgController@apply` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/nl-parse` | `-` | `NlOrgController@parse` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/ritual-runs` | `-` | `OrgRitualRunController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| GET | `agent/api/v1/org/ritual-runs/{id}` | `-` | `OrgRitualRunController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| POST | `agent/api/v1/org/ritual-runs/{id}/retry` | `-` | `OrgRitualRunController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| POST | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org` |
| PUT | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| DELETE | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/pause` | `-` | `OrgRitualController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/restore` | `-` | `OrgRitualController@restore` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/resume` | `-` | `OrgRitualController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| POST | `agent/api/v1/org/rituals/{id}/run` | `-` | `OrgRitualController@run` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, org, throttle:agent-mutations` |
| GET | `agent/api/v1/runs` | `-` | `AgentRunController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/runs/{id}` | `-` | `AgentRunController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/runs/{id}/confirm-lesson` | `-` | `AgentRunController@confirmSuggestedLesson` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/runs/{id}/events` | `-` | `AgentRunController@events` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/runs/{id}/retry` | `-` | `AgentRunController@retry` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/runs/{id}/stop` | `-` | `AgentRunController@stop` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/runtime/policy` | `-` | `RuntimePolicyController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/security/audit` | `-` | `SecurityAuditController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/services` | `-` | `ServiceManagerController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/services/restart` | `-` | `ServiceManagerController@restart` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/skills` | `-` | `SkillController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/skills/dashboard/health` | `-` | `SkillDashboardController@health` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/skills/dashboard/usage` | `-` | `SkillDashboardController@usage` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/skills/install` | `-` | `SkillController@install` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/skills/library` | `-` | `SkillController@library` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/skills/library/{slug}/install` | `-` | `SkillController@installFromLibrary` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/skills/{id}` | `-` | `SkillController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| PATCH | `agent/api/v1/skills/{id}` | `-` | `SkillController@update` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| DELETE | `agent/api/v1/skills/{id}` | `-` | `SkillController@destroy` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/skills/{id}/validate` | `-` | `SkillController@revalidate` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| POST | `agent/api/v1/system/directory-picker` | `-` | `SystemDirectoryPickerController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/telemetry/replay/active-build` | `-` | `ProjectionReplayBuildController@activeBuild` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/telemetry/replay/builds` | `-` | `ProjectionReplayBuildController@store` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/telemetry/replay/builds/{buildId}` | `-` | `ProjectionReplayBuildController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/telemetry/replay/builds/{buildId}/activate` | `-` | `ProjectionReplayBuildController@activate` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/workflows/{workflowKey}/cost` | `-` | `WorkflowCostController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/workflows/{workflowKey}/escalations` | `-` | `WorkflowEscalationController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| GET | `agent/api/v1/workflows/{workflowKey}/gate-transitions` | `-` | `WorkflowGateTransitionController@index` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/workflows/{workflowKey}/pause` | `-` | `WorkflowGovernanceController@pause` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |
| GET | `agent/api/v1/workflows/{workflowKey}/reliability` | `-` | `WorkflowReliabilityController@show` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license` |
| POST | `agent/api/v1/workflows/{workflowKey}/resume` | `-` | `WorkflowGovernanceController@resume` | `api, App\Http\Middleware\AgentApiVersionHeader, auth:sanctum, license, throttle:agent-mutations` |

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.api.connectors.discord.webhook` | ok | `agent/api/v1/connectors/discord/webhook` | `POST` |
| `agent.api.connectors.slack.webhook` | ok | `agent/api/v1/connectors/slack/webhook` | `POST` |
| `agent.api.connectors.telegram.webhook` | ok | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `POST` |
| `agent.api.connectors.whatsapp.webhook` | ok | `agent/api/v1/connectors/whatsapp/webhook` | `GET,POST` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/connectors`**
  - Controller: `ConnectorLibraryController@index`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/connectors/callback`**
  - Controller: `ConnectorOAuthCallbackController`
- **`POST agent/api/v1/connectors/discord/webhook`**
  - Controller: `WebhookController@handleDiscord`
- **`POST agent/api/v1/connectors/slack/webhook`**
  - Controller: `WebhookController@handleSlack`
- **`POST agent/api/v1/connectors/telegram/webhook/{accountKey}`**
  - Controller: `WebhookController@handleTelegram`
- **`GET,POST agent/api/v1/connectors/whatsapp/webhook`**
  - Controller: `WebhookController@handleWhatsApp`
- **`GET agent/api/v1/connectors/{id}`**
  - Controller: `ConnectorLibraryController@show`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/connectors/{id}/actions`**
  - Controller: `ConnectorLibraryController@actions`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/connectors/{id}/connect`**
  - Controller: `ConnectorConnectionController@connect`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/connectors/{id}/disconnect`**
  - Controller: `ConnectorConnectionController@disconnect`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/connectors/{id}/health`**
  - Controller: `ConnectorConnectionController@health`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/connectors/{id}/telemetry`**
  - Controller: `ConnectorTelemetryController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/connectors/{id}/test`**
  - Controller: `ConnectorConnectionController@test`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `api.tokens.default_expiration_days` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
