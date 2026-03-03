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
reviewed_at: 2026-03-03
---
# Agent API v1 Surface Reference

This guide is generated from live route registration and summarizes the API by operational domain.

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

### Backups

Registered endpoints: 3

| Method | URI | Route Name |
| --- | --- | --- |
| POST | `agent/api/v1/backups/run-now` | `-` |
| GET | `agent/api/v1/backups/settings` | `-` |
| PUT | `agent/api/v1/backups/settings` | `-` |

### Chat

Registered endpoints: 8

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/chat/actions/{id}` | `-` |
| POST | `agent/api/v1/chat/actions/{id}/cancel` | `-` |
| POST | `agent/api/v1/chat/actions/{id}/confirm` | `-` |
| GET | `agent/api/v1/chat/actions/{id}/status` | `-` |
| GET | `agent/api/v1/chat/sessions` | `-` |
| GET | `agent/api/v1/chat/sessions/{id}` | `-` |
| GET | `agent/api/v1/chat/sessions/{id}/actions` | `-` |
| GET | `agent/api/v1/chat/sessions/{id}/messages` | `-` |

### Compliance

Registered endpoints: 2

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/compliance/metrics` | `-` |
| GET | `agent/api/v1/compliance/status` | `-` |

### Connectors

Registered endpoints: 4

| Method | URI | Route Name |
| --- | --- | --- |
| POST | `agent/api/v1/connectors/discord/webhook` | `agent.api.connectors.discord.webhook` |
| POST | `agent/api/v1/connectors/slack/webhook` | `agent.api.connectors.slack.webhook` |
| POST | `agent/api/v1/connectors/telegram/webhook/{accountKey}` | `agent.api.connectors.telegram.webhook` |
| GET,POST | `agent/api/v1/connectors/whatsapp/webhook` | `agent.api.connectors.whatsapp.webhook` |

### Dashboard

Registered endpoints: 1

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/dashboard/metrics` | `-` |

### Delegation

Registered endpoints: 21

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/delegation/delegatee-profiles` | `-` |
| POST | `agent/api/v1/delegation/delegatee-profiles` | `-` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` |
| PUT | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` |
| DELETE | `agent/api/v1/delegation/delegatee-profiles/{id}` | `-` |
| POST | `agent/api/v1/delegation/delegatee-profiles/{id}/restore` | `-` |
| GET | `agent/api/v1/delegation/delegatee-profiles/{id}/trust` | `-` |
| GET | `agent/api/v1/delegation/graphs` | `-` |
| POST | `agent/api/v1/delegation/graphs` | `-` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks` | `-` |
| GET | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}` | `-` |
| POST | `agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}/verification/resolve` | `-` |
| GET | `agent/api/v1/delegation/graphs/{id}` | `-` |
| PUT | `agent/api/v1/delegation/graphs/{id}` | `-` |
| DELETE | `agent/api/v1/delegation/graphs/{id}` | `-` |
| POST | `agent/api/v1/delegation/graphs/{id}/cancel` | `-` |
| POST | `agent/api/v1/delegation/graphs/{id}/clone` | `-` |
| GET | `agent/api/v1/delegation/graphs/{id}/events` | `-` |
| POST | `agent/api/v1/delegation/graphs/{id}/restore` | `-` |
| POST | `agent/api/v1/delegation/graphs/{id}/start` | `-` |
| POST | `agent/api/v1/delegation/graphs/{id}/validate` | `-` |

### Docs

Registered endpoints: 4

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/docs/coverage` | `-` |
| GET | `agent/api/v1/docs/diagnostics` | `-` |
| GET | `agent/api/v1/docs/fragments/{uiKey}` | `-` |
| GET | `agent/api/v1/docs/search` | `-` |

### Features

Registered endpoints: 2

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/features/settings` | `-` |
| PUT | `agent/api/v1/features/settings` | `-` |

### Health

Registered endpoints: 3

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/health` | `-` |
| GET | `agent/api/v1/health/messenger` | `-` |
| GET | `agent/api/v1/health/scheduler` | `-` |

### Interrogation

Registered endpoints: 45

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/interrogation/sessions` | `-` |
| POST | `agent/api/v1/interrogation/sessions` | `-` |
| GET | `agent/api/v1/interrogation/sessions/{id}` | `-` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}` | `-` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/advance-pre-discovery` | `-` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/annotations` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/answer/edit` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-build-tasks` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/approve-plan` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks` | `-` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build-tasks/{taskId}/regenerate` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/build/clarify` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/cleanup-invalid-questions` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/confirm-summary` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/continue-interrogation` | `-` |
| GET | `agent/api/v1/interrogation/sessions/{id}/events` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-plan` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/export-summary` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-build-tasks` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/generate-plan` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/pause-build` | `-` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/oauth/start` | `-` |
| GET | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/projects` | `-` |
| PATCH | `agent/api/v1/interrogation/sessions/{id}/providers/{driver}/settings` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/regenerate-plan` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restart-from-beginning` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/restore` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/resume-build` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/retry` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-plan` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/revise-summary` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-build` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/start-discovery` | `-` |
| POST | `agent/api/v1/interrogation/sessions/{id}/tech-stacks` | `-` |
| DELETE | `agent/api/v1/interrogation/sessions/{id}/tech-stacks/{stackId}` | `-` |
| GET | `agent/api/v1/interrogation/settings` | `-` |
| GET | `agent/api/v1/interrogation/settings/{key}` | `-` |
| PUT | `agent/api/v1/interrogation/settings/{key}` | `-` |

### Jobs

Registered endpoints: 9

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/jobs` | `-` |
| POST | `agent/api/v1/jobs` | `-` |
| GET | `agent/api/v1/jobs/{id}` | `-` |
| PUT | `agent/api/v1/jobs/{id}` | `-` |
| DELETE | `agent/api/v1/jobs/{id}` | `-` |
| POST | `agent/api/v1/jobs/{id}/restore` | `-` |
| POST | `agent/api/v1/jobs/{id}/run-now` | `-` |
| GET | `agent/api/v1/jobs/{id}/runs` | `-` |
| POST | `agent/api/v1/jobs/{id}/toggle` | `-` |

### Memory

Registered endpoints: 12

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/memory/core-blocks` | `-` |
| GET | `agent/api/v1/memory/core-blocks/{key}` | `-` |
| PUT | `agent/api/v1/memory/core-blocks/{key}` | `-` |
| DELETE | `agent/api/v1/memory/core-blocks/{key}` | `-` |
| POST | `agent/api/v1/memory/retrieve` | `-` |
| GET | `agent/api/v1/memory/settings` | `-` |
| PUT | `agent/api/v1/memory/settings` | `-` |
| GET | `agent/api/v1/memory/settings/capabilities` | `-` |
| POST | `agent/api/v1/memory/settings/test-connection` | `-` |
| GET | `agent/api/v1/memory/stats` | `-` |
| POST | `agent/api/v1/memory/working/append` | `-` |
| GET | `agent/api/v1/memory/working/{runId}` | `-` |

### Messenger

Registered endpoints: 8

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/messenger/connectors` | `-` |
| POST | `agent/api/v1/messenger/connectors` | `-` |
| GET | `agent/api/v1/messenger/connectors/schema` | `-` |
| GET | `agent/api/v1/messenger/connectors/{id}` | `-` |
| PUT | `agent/api/v1/messenger/connectors/{id}` | `-` |
| DELETE | `agent/api/v1/messenger/connectors/{id}` | `-` |
| POST | `agent/api/v1/messenger/connectors/{id}/test` | `-` |
| GET | `agent/api/v1/messenger/metrics` | `-` |

### Notifications

Registered endpoints: 4

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/notifications` | `-` |
| DELETE | `agent/api/v1/notifications` | `-` |
| POST | `agent/api/v1/notifications/read-all` | `-` |
| POST | `agent/api/v1/notifications/{id}/read` | `-` |

### Org

Registered endpoints: 26

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/org/agents` | `-` |
| POST | `agent/api/v1/org/agents` | `-` |
| GET | `agent/api/v1/org/agents/{id}` | `-` |
| PUT | `agent/api/v1/org/agents/{id}` | `-` |
| DELETE | `agent/api/v1/org/agents/{id}` | `-` |
| POST | `agent/api/v1/org/agents/{id}/restore` | `-` |
| GET | `agent/api/v1/org/costs/summary` | `-` |
| GET | `agent/api/v1/org/councils` | `-` |
| POST | `agent/api/v1/org/councils` | `-` |
| GET | `agent/api/v1/org/councils/{id}` | `-` |
| PUT | `agent/api/v1/org/councils/{id}` | `-` |
| DELETE | `agent/api/v1/org/councils/{id}` | `-` |
| GET | `agent/api/v1/org/escalations` | `-` |
| POST | `agent/api/v1/org/escalations/{id}/resolve` | `-` |
| GET | `agent/api/v1/org/ritual-runs` | `-` |
| GET | `agent/api/v1/org/ritual-runs/{id}` | `-` |
| POST | `agent/api/v1/org/ritual-runs/{id}/retry` | `-` |
| GET | `agent/api/v1/org/rituals` | `-` |
| POST | `agent/api/v1/org/rituals` | `-` |
| GET | `agent/api/v1/org/rituals/{id}` | `-` |
| PUT | `agent/api/v1/org/rituals/{id}` | `-` |
| DELETE | `agent/api/v1/org/rituals/{id}` | `-` |
| POST | `agent/api/v1/org/rituals/{id}/pause` | `-` |
| POST | `agent/api/v1/org/rituals/{id}/restore` | `-` |
| POST | `agent/api/v1/org/rituals/{id}/resume` | `-` |
| POST | `agent/api/v1/org/rituals/{id}/run` | `-` |

### Repo Analysis

Registered endpoints: 20

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/repo-analysis/sessions` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions` | `-` |
| GET | `agent/api/v1/repo-analysis/sessions/{id}` | `-` |
| PATCH | `agent/api/v1/repo-analysis/sessions/{id}` | `-` |
| DELETE | `agent/api/v1/repo-analysis/sessions/{id}` | `-` |
| GET | `agent/api/v1/repo-analysis/sessions/{id}/artifacts` | `-` |
| GET | `agent/api/v1/repo-analysis/sessions/{id}/events` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/execute` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/generate-report` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/pause` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/plan` | `-` |
| GET | `agent/api/v1/repo-analysis/sessions/{id}/reports` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/restart-from-beginning` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/restore` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/resume` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/retry` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/retry-task` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/start-snapshot` | `-` |
| GET | `agent/api/v1/repo-analysis/sessions/{id}/tasks` | `-` |
| POST | `agent/api/v1/repo-analysis/sessions/{id}/validate-coverage` | `-` |

### Runs

Registered endpoints: 6

| Method | URI | Route Name |
| --- | --- | --- |
| GET | `agent/api/v1/runs` | `-` |
| GET | `agent/api/v1/runs/{id}` | `-` |
| POST | `agent/api/v1/runs/{id}/confirm-lesson` | `-` |
| GET | `agent/api/v1/runs/{id}/events` | `-` |
| POST | `agent/api/v1/runs/{id}/retry` | `-` |
| POST | `agent/api/v1/runs/{id}/stop` | `-` |

## Related Docs

- [API Token and Integration Flows](/docs/api-token-integration-flows)
- [Agent API v1 Route Inventory](/docs/agent-api-v1-route-inventory)

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
