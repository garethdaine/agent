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
reviewed_at: 2026-03-02
---
# Agent API v1 Route Inventory

This inventory is generated from `php artisan route:list --path=agent/api/v1 --json` and documents the currently registered API surface.

## Settings

The API is versioned via `X-Agent-Api-Version: 1.0` and protected by `auth:sanctum` for authenticated routes.

## Example

Run `php artisan route:list --path=agent/api/v1 --json` after route changes and update this inventory.

## Troubleshooting

If an expected endpoint is missing, confirm route registration in `routes/api.php` and feature-gate middleware state.

## Complete Route Table

| Method | URI | Route Name | Controller |
| --- | --- | --- | --- |
| POST | `agent/api/v1/backups/run-now` | `-` | `AgentBackupSettingsController@runNow` |
| GET | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@show` |
| PUT | `agent/api/v1/backups/settings` | `-` | `AgentBackupSettingsController@update` |
| GET | `agent/api/v1/chat/actions/{id}` | `-` | `ChatActionController@show` |
| POST | `agent/api/v1/chat/actions/{id}/cancel` | `-` | `ChatActionController@cancel` |
| POST | `agent/api/v1/chat/actions/{id}/confirm` | `-` | `ChatActionController@confirm` |
| GET | `agent/api/v1/chat/actions/{id}/status` | `-` | `ChatActionController@status` |
| GET | `agent/api/v1/chat/sessions` | `-` | `ChatSessionController@index` |
| GET | `agent/api/v1/chat/sessions/{id}` | `-` | `ChatSessionController@show` |
| GET | `agent/api/v1/chat/sessions/{id}/actions` | `-` | `ChatSessionController@actions` |
| GET | `agent/api/v1/chat/sessions/{id}/messages` | `-` | `ChatSessionController@messages` |
| GET | `agent/api/v1/compliance/metrics` | `-` | `ComplianceController@metrics` |
| GET | `agent/api/v1/compliance/status` | `-` | `ComplianceController@status` |
| POST | `agent/api/v1/connectors/discord/webhook` | `agent.api.connectors.discord.webhook` | `WebhookController@handleDiscord` |
| POST | `agent/api/v1/connectors/slack/webhook` | `agent.api.connectors.slack.webhook` | `WebhookController@handleSlack` |
| POST | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `agent.api.connectors.telegram.webhook` | `WebhookController@handleTelegram` |
| GET,POST | `agent/api/v1/connectors/whatsapp/webhook` | `agent.api.connectors.whatsapp.webhook` | `WebhookController@handleWhatsApp` |
| GET | `agent/api/v1/dashboard/metrics` | `-` | `AgentRunController@dashboardMetrics` |
| GET | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@index` |
| POST | `agent/api/v1/delegation/delegatee-profiles` | `-` | `DelegateeProfileController@store` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@show` |
| PUT | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@update` |
| DELETE | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` | `DelegateeProfileController@destroy` |
| POST | `agent/api/v1/delegation/delegatee-profiles/{id}/restore` | `-` | `DelegateeProfileController@restore` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}/trust` | `-` | `DelegateeProfileController@trust` |
| GET | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@index` |
| POST | `agent/api/v1/delegation/graphs` | `-` | `DelegationGraphController@store` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks` | `-` | `DelegationTaskController@index` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}` | `-` | `DelegationTaskController@show` |
| POST | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}/verification/resolve` | `-` | `DelegationTaskController@resolveVerification` |
| GET | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@show` |
| PUT | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@update` |
| DELETE | `agent/api/v1/delegation/graphs/{id}` | `-` | `DelegationGraphController@destroy` |
| POST | `agent/api/v1/delegation/graphs/{id}/cancel` | `-` | `DelegationGraphController@cancel` |
| POST | `agent/api/v1/delegation/graphs/{id}/clone` | `-` | `DelegationGraphController@clone` |
| GET | `agent/api/v1/delegation/graphs/{id}/events` | `-` | `DelegationGraphController@events` |
| POST | `agent/api/v1/delegation/graphs/{id}/restore` | `-` | `DelegationGraphController@restore` |
| POST | `agent/api/v1/delegation/graphs/{id}/start` | `-` | `DelegationGraphController@start` |
| POST | `agent/api/v1/delegation/graphs/{id}/validate` | `-` | `DelegationGraphController@validate` |
| GET | `agent/api/v1/docs/coverage` | `-` | `DocsCoverageController@index` |
| GET | `agent/api/v1/docs/diagnostics` | `-` | `DiagnosticsController` |
| GET | `agent/api/v1/docs/fragments/{uiKey}` | `-` | `DocsFragmentController@show` |
| GET | `agent/api/v1/docs/search` | `-` | `DocsSearchController@index` |
| GET | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@index` |
| PUT | `agent/api/v1/features/settings` | `-` | `AgentFeatureSettingsController@update` |
| GET | `agent/api/v1/health` | `-` | `Closure` |
| GET | `agent/api/v1/health/messenger` | `-` | `MessengerHealthController@index` |
| GET | `agent/api/v1/health/scheduler` | `-` | `AgentRunController@schedulerHealth` |
| GET | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@index` |
| POST | `agent/api/v1/interrogation/sessions` | `-` | `InterrogationSessionController@store` |
| GET | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@show` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@update` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}` | `-` | `InterrogationSessionController@destroy` |
| POST | `agent/api/v1/interrogation/sessions/{id}/advance-pre-discovery` | `-` | `InterrogationSessionController@advancePreDiscovery` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/annotations` | `-` | `InterrogationSessionController@updateAnnotation` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer` | `-` | `InterrogationSessionController@submitAnswer` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer/edit` | `-` | `InterrogationSessionController@editAnswer` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-build-tasks` | `-` | `InterrogationSessionController@approveBuildTasks` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-plan` | `-` | `InterrogationSessionController@approvePlan` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks` | `-` | `InterrogationSessionController@storeBuildTask` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@updateBuildTask` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` | `InterrogationSessionController@destroyBuildTask` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}/regenerate` | `-` | `InterrogationSessionController@regenerateBuildTask` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build/clarify` | `-` | `InterrogationSessionController@clarifyBuild` |
| POST | `agent/api/v1/interrogation/sessions/{id}/cleanup-invalid-questions` | `-` | `InterrogationSessionController@cleanupInvalidQuestions` |
| POST | `agent/api/v1/interrogation/sessions/{id}/confirm-summary` | `-` | `InterrogationSessionController@confirmSummary` |
| POST | `agent/api/v1/interrogation/sessions/{id}/continue-interrogation` | `-` | `InterrogationSessionController@continueInterrogation` |
| GET | `agent/api/v1/interrogation/sessions/{id}/events` | `-` | `InterrogationSessionController@events` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-plan` | `-` | `InterrogationSessionController@exportPlan` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-summary` | `-` | `InterrogationSessionController@exportSummary` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-build-tasks` | `-` | `InterrogationSessionController@generateBuildTasks` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-plan` | `-` | `InterrogationSessionController@generatePlan` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause` | `-` | `InterrogationSessionController@pause` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause-build` | `-` | `InterrogationSessionController@pauseBuild` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}` | `-` | `InterrogationTaskProviderController@disconnect` |
| POST | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/oauth/start` | `-` | `InterrogationTaskProviderController@startOAuth` |
| GET | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/projects` | `-` | `InterrogationTaskProviderController@projects` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/settings` | `-` | `InterrogationTaskProviderController@updateSettings` |
| POST | `agent/api/v1/interrogation/sessions/{id}/regenerate-plan` | `-` | `InterrogationSessionController@regeneratePlan` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restart-from-beginning` | `-` | `InterrogationSessionController@restartFromBeginning` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restore` | `-` | `InterrogationSessionController@restore` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume` | `-` | `InterrogationSessionController@resume` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume-build` | `-` | `InterrogationSessionController@resumeBuild` |
| POST | `agent/api/v1/interrogation/sessions/{id}/retry` | `-` | `InterrogationSessionController@retry` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-plan` | `-` | `InterrogationSessionController@requestRevision` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-summary` | `-` | `InterrogationSessionController@reviseSummary` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-build` | `-` | `InterrogationSessionController@startBuild` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-discovery` | `-` | `InterrogationSessionController@startDiscovery` |
| POST | `agent/api/v1/interrogation/sessions/{id}/tech-stacks` | `-` | `InterrogationTechStackController@store` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/tech-stacks/{stackId}` | `-` | `InterrogationTechStackController@destroy` |
| GET | `agent/api/v1/interrogation/settings` | `-` | `InterrogationSettingsController@index` |
| GET | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@show` |
| PUT | `agent/api/v1/interrogation/settings/{key}` | `-` | `InterrogationSettingsController@update` |
| GET | `agent/api/v1/jobs` | `-` | `AgentJobController@index` |
| POST | `agent/api/v1/jobs` | `-` | `AgentJobController@store` |
| GET | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@show` |
| PUT | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@update` |
| DELETE | `agent/api/v1/jobs/{id}` | `-` | `AgentJobController@destroy` |
| POST | `agent/api/v1/jobs/{id}/restore` | `-` | `AgentJobController@restore` |
| POST | `agent/api/v1/jobs/{id}/run-now` | `-` | `AgentJobController@runNow` |
| GET | `agent/api/v1/jobs/{id}/runs` | `-` | `AgentJobController@runs` |
| POST | `agent/api/v1/jobs/{id}/toggle` | `-` | `AgentJobController@toggle` |
| GET | `agent/api/v1/memory/core-blocks` | `-` | `MemoryCoreBlockController@index` |
| GET | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@show` |
| PUT | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@update` |
| DELETE | `agent/api/v1/memory/core-blocks/{key}` | `-` | `MemoryCoreBlockController@destroy` |
| POST | `agent/api/v1/memory/retrieve` | `-` | `MemoryRetrievalController@retrieve` |
| GET | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@index` |
| PUT | `agent/api/v1/memory/settings` | `-` | `MemorySettingsController@update` |
| GET | `agent/api/v1/memory/settings/capabilities` | `-` | `MemorySettingsController@capabilities` |
| POST | `agent/api/v1/memory/settings/test-connection` | `-` | `MemorySettingsController@testConnection` |
| GET | `agent/api/v1/memory/stats` | `-` | `MemoryDiagnosticsController@stats` |
| POST | `agent/api/v1/memory/working/append` | `-` | `MemoryWorkingController@append` |
| GET | `agent/api/v1/memory/working/{runId}` | `-` | `MemoryWorkingController@show` |
| GET | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@index` |
| POST | `agent/api/v1/messenger/connectors` | `-` | `MessengerConnectorController@store` |
| GET | `agent/api/v1/messenger/connectors/schema` | `-` | `MessengerConnectorController@schema` |
| GET | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@show` |
| PUT | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@update` |
| DELETE | `agent/api/v1/messenger/connectors/{id}` | `-` | `MessengerConnectorController@destroy` |
| POST | `agent/api/v1/messenger/connectors/{id}/test` | `-` | `MessengerConnectorController@test` |
| GET | `agent/api/v1/messenger/metrics` | `-` | `MessengerMetricsController@index` |
| GET | `agent/api/v1/notifications` | `-` | `NotificationController@index` |
| DELETE | `agent/api/v1/notifications` | `-` | `NotificationController@clearAll` |
| POST | `agent/api/v1/notifications/read-all` | `-` | `NotificationController@markAllAsRead` |
| POST | `agent/api/v1/notifications/{id}/read` | `-` | `NotificationController@markAsRead` |
| GET | `agent/api/v1/org/agents` | `-` | `OrgAgentController@index` |
| POST | `agent/api/v1/org/agents` | `-` | `OrgAgentController@store` |
| GET | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@show` |
| PUT | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@update` |
| DELETE | `agent/api/v1/org/agents/{id}` | `-` | `OrgAgentController@destroy` |
| POST | `agent/api/v1/org/agents/{id}/restore` | `-` | `OrgAgentController@restore` |
| GET | `agent/api/v1/org/costs/summary` | `-` | `OrgCostController@summary` |
| GET | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@index` |
| POST | `agent/api/v1/org/councils` | `-` | `OrgCouncilController@store` |
| GET | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@show` |
| PUT | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@update` |
| DELETE | `agent/api/v1/org/councils/{id}` | `-` | `OrgCouncilController@destroy` |
| GET | `agent/api/v1/org/escalations` | `-` | `OrgEscalationController@index` |
| POST | `agent/api/v1/org/escalations/{id}/resolve` | `-` | `OrgEscalationController@resolve` |
| GET | `agent/api/v1/org/ritual-runs` | `-` | `OrgRitualRunController@index` |
| GET | `agent/api/v1/org/ritual-runs/{id}` | `-` | `OrgRitualRunController@show` |
| POST | `agent/api/v1/org/ritual-runs/{id}/retry` | `-` | `OrgRitualRunController@retry` |
| GET | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@index` |
| POST | `agent/api/v1/org/rituals` | `-` | `OrgRitualController@store` |
| GET | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@show` |
| PUT | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@update` |
| DELETE | `agent/api/v1/org/rituals/{id}` | `-` | `OrgRitualController@destroy` |
| POST | `agent/api/v1/org/rituals/{id}/pause` | `-` | `OrgRitualController@pause` |
| POST | `agent/api/v1/org/rituals/{id}/restore` | `-` | `OrgRitualController@restore` |
| POST | `agent/api/v1/org/rituals/{id}/resume` | `-` | `OrgRitualController@resume` |
| POST | `agent/api/v1/org/rituals/{id}/run` | `-` | `OrgRitualController@run` |
| GET | `agent/api/v1/runs` | `-` | `AgentRunController@index` |
| GET | `agent/api/v1/runs/{id}` | `-` | `AgentRunController@show` |
| POST | `agent/api/v1/runs/{id}/confirm-lesson` | `-` | `AgentRunController@confirmSuggestedLesson` |
| GET | `agent/api/v1/runs/{id}/events` | `-` | `AgentRunController@events` |
| POST | `agent/api/v1/runs/{id}/retry` | `-` | `AgentRunController@retry` |
| POST | `agent/api/v1/runs/{id}/stop` | `-` | `AgentRunController@stop` |
