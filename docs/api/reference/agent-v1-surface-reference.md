---
slug: agent-api-v1-surface-reference
title: Agent API v1 Surface Reference
summary: Operator-focused API reference covering domain groups, auth model, rate limiting, and endpoint usage patterns.
section: api
audience: developer
status: published
version: "1.0.0"
tags:
  - api
  - reference
  - contracts
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
reviewed_at: 2026-03-06
---

## Settings

`api.tokens.default_expiration_days` determines baseline token lifespan for API integrations.

| Setting | Purpose | Typical Value |
| --- | --- | --- |
| `api.tokens.default_expiration_days` | Default token expiry window | `30` |

## Example

When onboarding an integration, validate read endpoints first, then mutation endpoints with throttling/backoff handling.

## Troubleshooting

- `401/419`: verify Sanctum auth/session and CSRF boundaries.
- `429`: respect endpoint throttles with retry backoff.
- `503` on docs search: verify Typesense health and reindex queue.

## Domain Groups

### Audit Log

**1 endpoint(s)** registered under `/agent/api/v1/audit-log`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/audit-log` | `-` | `AuditLogController@index` | `auth:sanctum` |

### Backups

**3 endpoint(s)** registered under `/agent/api/v1/backups`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| POST | `agent/api/v1/backups/run-now` | `-` | `AgentBackupSettingsController@runNow` | `auth:sanctum` |
| GET | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@update` | `auth:sanctum` |

### Chat

**18 endpoint(s)** registered under `/agent/api/v1/chat`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/chat/actions/{id}` | `-` | `ChatActionController@show` | `auth:sanctum` |
| POST | `agent/api/v1/chat/actions/{id}/cancel` | `-` | `ChatActionController@cancel` | `auth:sanctum` |
| POST | `agent/api/v1/chat/actions/{id}/confirm` | `-` | `ChatActionController@confirm` | `auth:sanctum` |
| GET | `agent/api/v1/chat/actions/{id}/status` | `-` | `ChatActionController@status` | `auth:sanctum` |
| GET | `agent/api/v1/chat/commands` | `-` | `ChatSessionController@commands` | `auth:sanctum` |
| GET | `agent/api/v1/chat/connectors` | `-` | `ChatSessionController@connectors` | `auth:sanctum` |
| GET | `agent/api/v1/chat/runtime/sessions` | `-` | `RuntimeSessionController@index` | `auth:sanctum` |
| GET | `agent/api/v1/chat/runtime/sessions/{id}` | `-` | `RuntimeSessionController@show` | `auth:sanctum` |
| POST | `agent/api/v1/chat/runtime/sessions/{id}/stop` | `-` | `RuntimeSessionController@stop` | `auth:sanctum` |
| POST | `agent/api/v1/chat/runtime/tool-calls/{id}/approve` | `-` | `RuntimeToolCallController@approve` | `auth:sanctum` |
| POST | `agent/api/v1/chat/runtime/tool-calls/{id}/deny` | `-` | `RuntimeToolCallController@deny` | `auth:sanctum` |
| GET | `agent/api/v1/chat/sessions` | `-` | `ChatSessionController@index` | `auth:sanctum` |
| POST | `agent/api/v1/chat/sessions` | `-` | `ChatSessionController@store` | `auth:sanctum` |
| GET | `agent/api/v1/chat/sessions/{id}` | `-` | `ChatSessionController@show` | `auth:sanctum` |
| GET | `agent/api/v1/chat/sessions/{id}/actions` | `-` | `ChatSessionController@actions` | `auth:sanctum` |
| POST | `agent/api/v1/chat/sessions/{id}/archive` | `-` | `ChatSessionController@archive` | `auth:sanctum` |
| GET | `agent/api/v1/chat/sessions/{id}/messages` | `-` | `ChatSessionController@messages` | `auth:sanctum` |
| POST | `agent/api/v1/chat/sessions/{id}/send` | `-` | `ChatSessionController@send` | `auth:sanctum` |

### Code Analysis

**21 endpoint(s)** registered under `/agent/api/v1/code-analysis`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/code-analysis/sessions` | `-` | `RepoAnalysisSessionController@index` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions` | `-` | `RepoAnalysisSessionController@store` | `auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@show` | `auth:sanctum` |
| PATCH | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/code-analysis/sessions/{id}` | `-` | `RepoAnalysisSessionController@destroy` | `auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/artifacts` | `-` | `RepoAnalysisSessionController@artifacts` | `auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/events` | `-` | `RepoAnalysisSessionController@events` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/execute` | `-` | `RepoAnalysisSessionController@execute` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/generate-report` | `-` | `RepoAnalysisSessionController@generateReport` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/pause` | `-` | `RepoAnalysisSessionController@pause` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/plan` | `-` | `RepoAnalysisSessionController@plan` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/purge` | `-` | `RepoAnalysisSessionController@purge` | `auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/reports` | `-` | `RepoAnalysisSessionController@reports` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/restart-from-beginning` | `-` | `RepoAnalysisSessionController@restartFromBeginning` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/restore` | `-` | `RepoAnalysisSessionController@restore` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/resume` | `-` | `RepoAnalysisSessionController@resume` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/retry` | `-` | `RepoAnalysisSessionController@retry` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/retry-task` | `-` | `RepoAnalysisSessionController@retryTask` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/start-snapshot` | `-` | `RepoAnalysisSessionController@startSnapshot` | `auth:sanctum` |
| GET | `agent/api/v1/code-analysis/sessions/{id}/tasks` | `-` | `RepoAnalysisSessionController@tasks` | `auth:sanctum` |
| POST | `agent/api/v1/code-analysis/sessions/{id}/validate-coverage` | `-` | `RepoAnalysisSessionController@validateCoverage` | `auth:sanctum` |

### Compliance

**2 endpoint(s)** registered under `/agent/api/v1/compliance`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/compliance/metrics` | `-` | `ComplianceController@metrics` | `auth:sanctum` |
| GET | `agent/api/v1/compliance/status` | `-` | `ComplianceController@status` | `auth:sanctum` |

### Configuration

**2 endpoint(s)** registered under `/agent/api/v1/configuration`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/configuration` | `-` | `ConfigurationController@index` | `auth:sanctum` |
| PUT | `agent/api/v1/configuration` | `-` | `ConfigurationController@update` | `auth:sanctum` |

### Connectors

**4 endpoint(s)** registered under `/agent/api/v1/connectors`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| POST | `agent/api/v1/connectors/discord/webhook` | `agent.api.connectors.discord.webhook` | `WebhookController@handleDiscord` | - |
| POST | `agent/api/v1/connectors/slack/webhook` | `agent.api.connectors.slack.webhook` | `WebhookController@handleSlack` | - |
| POST | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `agent.api.connectors.telegram.webhook` | `WebhookController@handleTelegram` | - |
| GET,POST | `agent/api/v1/connectors/whatsapp/webhook` | `agent.api.connectors.whatsapp.webhook` | `WebhookController@handleWhatsApp` | - |

### Credentials

**3 endpoint(s)** registered under `/agent/api/v1/credentials`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/credentials` | `-` | `CredentialsController@index` | `auth:sanctum` |
| POST | `agent/api/v1/credentials` | `-` | `CredentialsController@store` | `auth:sanctum` |
| DELETE | `agent/api/v1/credentials` | `-` | `CredentialsController@destroy` | `auth:sanctum` |

### Dashboard

**1 endpoint(s)** registered under `/agent/api/v1/dashboard`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/dashboard/metrics` | `-` | `AgentRunController@dashboardMetrics` | `auth:sanctum` |

### Debug

**1 endpoint(s)** registered under `/agent/api/v1/debug`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/debug` | `-` | `DebugPanelController@index` | `auth:sanctum` |

### Delegation

**22 endpoint(s)** registered under `/agent/api/v1/delegation`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/delegation/capabilities` | `-` | `DelegateeProfileController@capabilities` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@index` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@store` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@destroy` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/delegatee-profiles/{id}/restore` | `-` | `DelegateeProfileController@restore` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}/trust` | `-` | `DelegateeProfileController@trust` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@index` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@store` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks` | `-` | `DelegationTaskController@index` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}` | `-` | `DelegationTaskController@show` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}/verification/resolve` | `-` | `DelegationTaskController@resolveVerification` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@destroy` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs/{id}/cancel` | `-` | `DelegationGraphController@cancel` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs/{id}/clone` | `-` | `DelegationGraphController@clone` | `auth:sanctum` |
| GET | `agent/api/v1/delegation/graphs/{id}/events` | `-` | `DelegationGraphController@events` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs/{id}/restore` | `-` | `DelegationGraphController@restore` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs/{id}/start` | `-` | `DelegationGraphController@start` | `auth:sanctum` |
| POST | `agent/api/v1/delegation/graphs/{id}/validate` | `-` | `DelegationGraphController@validate` | `auth:sanctum` |

### Deployments

**1 endpoint(s)** registered under `/agent/api/v1/deployments`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/deployments/counting` | `-` | `DeploymentCountingController@index` | `auth:sanctum` |

### Diagnostics

**1 endpoint(s)** registered under `/agent/api/v1/diagnostics`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/diagnostics` | `-` | `DiagnosticsController@index` | `auth:sanctum` |

### Docs

**4 endpoint(s)** registered under `/agent/api/v1/docs`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/docs/coverage` | `-` | `DocsCoverageController@index` | `auth:sanctum` |
| GET | `agent/api/v1/docs/diagnostics` | `-` | `DiagnosticsController` | `auth:sanctum` |
| GET | `agent/api/v1/docs/fragments/{uiKey}` | `-` | `DocsFragmentController@show` | `auth:sanctum` |
| GET | `agent/api/v1/docs/search` | `-` | `DocsSearchController@index` | `auth:sanctum` |

### Features

**2 endpoint(s)** registered under `/agent/api/v1/features`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@index` | `auth:sanctum` |
| PUT | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@update` | `auth:sanctum` |

### Health

**3 endpoint(s)** registered under `/agent/api/v1/health`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/health` | `-` | `Closure` | - |
| GET | `agent/api/v1/health/messenger` | `-` | `MessengerHealthController@index` | `auth:sanctum` |
| GET | `agent/api/v1/health/scheduler` | `-` | `AgentRunController@schedulerHealth` | `auth:sanctum` |

### Interrogation

**46 endpoint(s)** registered under `/agent/api/v1/interrogation`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@index` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@store` | `auth:sanctum` |
| GET | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@show` | `auth:sanctum` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@destroy` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/advance-pre-discovery` | `-` | `InterrogationSessionController@advancePreDiscovery` | `auth:sanctum` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/annotations` | `-` | `InterrogationSessionController@updateAnnotation` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer` | `-` | `InterrogationSessionController@submitAnswer` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer/edit` | `-` | `InterrogationSessionController@editAnswer` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-build-tasks` | `-` | `InterrogationSessionController@approveBuildTasks` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-plan` | `-` | `InterrogationSessionController@approvePlan` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks` | `-` | `InterrogationSessionController@storeBuildTask` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/reorder` | `-` | `InterrogationSessionController@reorderBuildTasks` | `auth:sanctum` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@updateBuildTask` | `auth:sanctum` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@destroyBuildTask` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}/regenerate` | `-` | `InterrogationSessionController@regenerateBuildTask` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build/clarify` | `-` | `InterrogationSessionController@clarifyBuild` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/cleanup-invalid-questions` | `-` | `InterrogationSessionController@cleanupInvalidQuestions` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/confirm-summary` | `-` | `InterrogationSessionController@confirmSummary` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/continue-interrogation` | `-` | `InterrogationSessionController@continueInterrogation` | `auth:sanctum` |
| GET | `agent/api/v1/interrogation/sessions/{id}/events` | `-` | `InterrogationSessionController@events` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-plan` | `-` | `InterrogationSessionController@exportPlan` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-summary` | `-` | `InterrogationSessionController@exportSummary` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-build-tasks` | `-` | `InterrogationSessionController@generateBuildTasks` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-plan` | `-` | `InterrogationSessionController@generatePlan` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause` | `-` | `InterrogationSessionController@pause` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause-build` | `-` | `InterrogationSessionController@pauseBuild` | `auth:sanctum` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}` | `-` | `InterrogationTaskProviderController@disconnect` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/oauth/start` | `-` | `InterrogationTaskProviderController@startOAuth` | `auth:sanctum` |
| GET | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/projects` | `-` | `InterrogationTaskProviderController@projects` | `auth:sanctum` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/settings` | `-` | `InterrogationTaskProviderController@updateSettings` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/regenerate-plan` | `-` | `InterrogationSessionController@regeneratePlan` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restart-from-beginning` | `-` | `InterrogationSessionController@restartFromBeginning` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restore` | `-` | `InterrogationSessionController@restore` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume` | `-` | `InterrogationSessionController@resume` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume-build` | `-` | `InterrogationSessionController@resumeBuild` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/retry` | `-` | `InterrogationSessionController@retry` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-plan` | `-` | `InterrogationSessionController@requestRevision` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-summary` | `-` | `InterrogationSessionController@reviseSummary` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-build` | `-` | `InterrogationSessionController@startBuild` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-discovery` | `-` | `InterrogationSessionController@startDiscovery` | `auth:sanctum` |
| POST | `agent/api/v1/interrogation/sessions/{id}/tech-stacks` | `-` | `InterrogationTechStackController@store` | `auth:sanctum` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/tech-stacks/{stackId}` | `-` | `InterrogationTechStackController@destroy` | `auth:sanctum` |
| GET | `agent/api/v1/interrogation/settings` | `-` | `InterrogationSettingsController@index` | `auth:sanctum` |
| GET | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@update` | `auth:sanctum` |

### Jobs

**10 endpoint(s)** registered under `/agent/api/v1/jobs`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/jobs` | `-` | `AgentJobController@index` | `auth:sanctum` |
| POST | `agent/api/v1/jobs` | `-` | `AgentJobController@store` | `auth:sanctum` |
| GET | `agent/api/v1/jobs/by-workflow/{workflowKey}` | `-` | `AgentJobController@showByWorkflowKey` | `auth:sanctum` |
| GET | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@destroy` | `auth:sanctum` |
| POST | `agent/api/v1/jobs/{id}/restore` | `-` | `AgentJobController@restore` | `auth:sanctum` |
| POST | `agent/api/v1/jobs/{id}/run-now` | `-` | `AgentJobController@runNow` | `auth:sanctum` |
| GET | `agent/api/v1/jobs/{id}/runs` | `-` | `AgentJobController@runs` | `auth:sanctum` |
| POST | `agent/api/v1/jobs/{id}/toggle` | `-` | `AgentJobController@toggle` | `auth:sanctum` |

### Logs

**2 endpoint(s)** registered under `/agent/api/v1/logs`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/logs` | `-` | `LogTailController@index` | `auth:sanctum` |
| GET | `agent/api/v1/logs/export` | `-` | `LogTailController@export` | `auth:sanctum` |

### Memory

**13 endpoint(s)** registered under `/agent/api/v1/memory`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/memory/core-blocks` | `-` | `MemoryCoreBlockController@index` | `auth:sanctum` |
| GET | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@destroy` | `auth:sanctum` |
| GET | `agent/api/v1/memory/models` | `-` | `MemoryModelsController` | `auth:sanctum` |
| POST | `agent/api/v1/memory/retrieve` | `-` | `MemoryRetrievalController@retrieve` | `auth:sanctum` |
| GET | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@index` | `auth:sanctum` |
| PUT | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@update` | `auth:sanctum` |
| GET | `agent/api/v1/memory/settings/capabilities` | `-` | `MemorySettingsController@capabilities` | `auth:sanctum` |
| POST | `agent/api/v1/memory/settings/test-connection` | `-` | `MemorySettingsController@testConnection` | `auth:sanctum` |
| GET | `agent/api/v1/memory/stats` | `-` | `MemoryDiagnosticsController@stats` | `auth:sanctum` |
| POST | `agent/api/v1/memory/working/append` | `-` | `MemoryWorkingController@append` | `auth:sanctum` |
| GET | `agent/api/v1/memory/working/{runId}` | `-` | `MemoryWorkingController@show` | `auth:sanctum` |

### Messenger

**14 endpoint(s)** registered under `/agent/api/v1/messenger`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@index` | `auth:sanctum` |
| POST | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@store` | `auth:sanctum` |
| GET | `agent/api/v1/messenger/connectors/schema` | `-` | `MessengerConnectorController@schema` | `auth:sanctum` |
| GET | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@destroy` | `auth:sanctum` |
| GET | `agent/api/v1/messenger/connectors/{id}/policy` | `-` | `ConnectorPolicyController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/messenger/connectors/{id}/policy` | `-` | `ConnectorPolicyController@update` | `auth:sanctum` |
| GET,PUT | `agent/api/v1/messenger/connectors/{id}/soul` | `-` | `MessengerConnectorController@soul` | `auth:sanctum` |
| POST | `agent/api/v1/messenger/connectors/{id}/test` | `-` | `MessengerConnectorController@test` | `auth:sanctum` |
| GET | `agent/api/v1/messenger/metrics` | `-` | `MessengerMetricsController@index` | `auth:sanctum` |
| GET | `agent/api/v1/messenger/pairings` | `-` | `PairingController@index` | `auth:sanctum` |
| POST | `agent/api/v1/messenger/pairings/{id}/approve` | `-` | `PairingController@approve` | `auth:sanctum` |
| POST | `agent/api/v1/messenger/pairings/{id}/revoke` | `-` | `PairingController@revoke` | `auth:sanctum` |

### N8N

**1 endpoint(s)** registered under `/agent/api/v1/n8n`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| POST | `agent/api/v1/n8n/webhook` | `-` | `N8nWebhookController` | - |

### Notifications

**4 endpoint(s)** registered under `/agent/api/v1/notifications`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/notifications` | `-` | `NotificationController@index` | `auth:sanctum` |
| DELETE | `agent/api/v1/notifications` | `-` | `NotificationController@clearAll` | `auth:sanctum` |
| POST | `agent/api/v1/notifications/read-all` | `-` | `NotificationController@markAllAsRead` | `auth:sanctum` |
| POST | `agent/api/v1/notifications/{id}/read` | `-` | `NotificationController@markAsRead` | `auth:sanctum` |

### Office

**1 endpoint(s)** registered under `/agent/api/v1/office`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/office/state` | `-` | `OfficeStateController` | `auth:sanctum` |

### Org

**26 endpoint(s)** registered under `/agent/api/v1/org`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/org/agents` | `-` | `OrgAgentController@index` | `auth:sanctum` |
| POST | `agent/api/v1/org/agents` | `-` | `OrgAgentController@store` | `auth:sanctum` |
| GET | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@destroy` | `auth:sanctum` |
| POST | `agent/api/v1/org/agents/{id}/restore` | `-` | `OrgAgentController@restore` | `auth:sanctum` |
| GET | `agent/api/v1/org/costs/summary` | `-` | `OrgCostController@summary` | `auth:sanctum` |
| GET | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@index` | `auth:sanctum` |
| POST | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@store` | `auth:sanctum` |
| GET | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@destroy` | `auth:sanctum` |
| GET | `agent/api/v1/org/escalations` | `-` | `OrgEscalationController@index` | `auth:sanctum` |
| POST | `agent/api/v1/org/escalations/{id}/resolve` | `-` | `OrgEscalationController@resolve` | `auth:sanctum` |
| GET | `agent/api/v1/org/ritual-runs` | `-` | `OrgRitualRunController@index` | `auth:sanctum` |
| GET | `agent/api/v1/org/ritual-runs/{id}` | `-` | `OrgRitualRunController@show` | `auth:sanctum` |
| POST | `agent/api/v1/org/ritual-runs/{id}/retry` | `-` | `OrgRitualRunController@retry` | `auth:sanctum` |
| GET | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@index` | `auth:sanctum` |
| POST | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@store` | `auth:sanctum` |
| GET | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@show` | `auth:sanctum` |
| PUT | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@update` | `auth:sanctum` |
| DELETE | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@destroy` | `auth:sanctum` |
| POST | `agent/api/v1/org/rituals/{id}/pause` | `-` | `OrgRitualController@pause` | `auth:sanctum` |
| POST | `agent/api/v1/org/rituals/{id}/restore` | `-` | `OrgRitualController@restore` | `auth:sanctum` |
| POST | `agent/api/v1/org/rituals/{id}/resume` | `-` | `OrgRitualController@resume` | `auth:sanctum` |
| POST | `agent/api/v1/org/rituals/{id}/run` | `-` | `OrgRitualController@run` | `auth:sanctum` |

### Runs

**6 endpoint(s)** registered under `/agent/api/v1/runs`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/runs` | `-` | `AgentRunController@index` | `auth:sanctum` |
| GET | `agent/api/v1/runs/{id}` | `-` | `AgentRunController@show` | `auth:sanctum` |
| POST | `agent/api/v1/runs/{id}/confirm-lesson` | `-` | `AgentRunController@confirmSuggestedLesson` | `auth:sanctum` |
| GET | `agent/api/v1/runs/{id}/events` | `-` | `AgentRunController@events` | `auth:sanctum` |
| POST | `agent/api/v1/runs/{id}/retry` | `-` | `AgentRunController@retry` | `auth:sanctum` |
| POST | `agent/api/v1/runs/{id}/stop` | `-` | `AgentRunController@stop` | `auth:sanctum` |

### Runtime

**1 endpoint(s)** registered under `/agent/api/v1/runtime`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/runtime/policy` | `-` | `RuntimePolicyController@index` | `auth:sanctum` |

### Security

**1 endpoint(s)** registered under `/agent/api/v1/security`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/security/audit` | `-` | `SecurityAuditController@index` | `auth:sanctum` |

### System

**1 endpoint(s)** registered under `/agent/api/v1/system`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| POST | `agent/api/v1/system/directory-picker` | `-` | `SystemDirectoryPickerController@store` | `auth:sanctum` |

### Telemetry

**4 endpoint(s)** registered under `/agent/api/v1/telemetry`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/telemetry/replay/active-build` | `-` | `ProjectionReplayBuildController@activeBuild` | `auth:sanctum` |
| POST | `agent/api/v1/telemetry/replay/builds` | `-` | `ProjectionReplayBuildController@store` | `auth:sanctum` |
| GET | `agent/api/v1/telemetry/replay/builds/{buildId}` | `-` | `ProjectionReplayBuildController@show` | `auth:sanctum` |
| POST | `agent/api/v1/telemetry/replay/builds/{buildId}/activate` | `-` | `ProjectionReplayBuildController@activate` | `auth:sanctum` |

### Workflows

**6 endpoint(s)** registered under `/agent/api/v1/workflows`.

| Method | URI | Route Name | Controller | Auth |
| --- | --- | --- | --- | --- |
| GET | `agent/api/v1/workflows/{workflowKey}/cost` | `-` | `WorkflowCostController@show` | `auth:sanctum` |
| GET | `agent/api/v1/workflows/{workflowKey}/escalations` | `-` | `WorkflowEscalationController@index` | `auth:sanctum` |
| GET | `agent/api/v1/workflows/{workflowKey}/gate-transitions` | `-` | `WorkflowGateTransitionController@index` | `auth:sanctum` |
| POST | `agent/api/v1/workflows/{workflowKey}/pause` | `-` | `WorkflowGovernanceController@pause` | `auth:sanctum` |
| GET | `agent/api/v1/workflows/{workflowKey}/reliability` | `-` | `WorkflowReliabilityController@show` | `auth:sanctum` |
| POST | `agent/api/v1/workflows/{workflowKey}/resume` | `-` | `WorkflowGovernanceController@resume` | `auth:sanctum` |

## Related Docs

- [API Token and Integration Flows](/docs/api-token-integration-flows)
- [Agent API v1 Route Inventory](/docs/agent-api-v1-route-inventory)

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

- **`POST agent/api/v1/connectors/discord/webhook`**
  - Controller: `WebhookController@handleDiscord`
- **`POST agent/api/v1/connectors/slack/webhook`**
  - Controller: `WebhookController@handleSlack`
- **`POST agent/api/v1/connectors/telegram/webhook/{accountKey}`**
  - Controller: `WebhookController@handleTelegram`
- **`GET,POST agent/api/v1/connectors/whatsapp/webhook`**
  - Controller: `WebhookController@handleWhatsApp`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `api.tokens.default_expiration_days` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
