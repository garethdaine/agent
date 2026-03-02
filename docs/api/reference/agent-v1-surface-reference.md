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
reviewed_at: 2026-03-02
---
# Agent API v1 Surface Reference

This guide explains the API by operational domain and points to full route inventory and endpoint-specific docs.

## Auth, Versioning, and Security

- Base prefix: `/agent/api/v1`.
- Version header: `X-Agent-Api-Version: 1.0`.
- Auth: `auth:sanctum` for authenticated routes.
- Write-heavy operations are throttled via `agent-mutations`, `interrogation`, `memory-writes`, and related limiters.

## Domain Groups

### Job and Run Operations

- Manage scheduled jobs and inspect execution runs/events.
- Typical routes: `/jobs`, `/jobs/{id}/run-now`, `/runs`, `/runs/{id}/events`.
- Reference: [List Jobs API](/docs/jobs-list-api).

### Dashboard and Health

- Operational metrics, scheduler health, and messenger health signals.
- Typical routes: `/dashboard/metrics`, `/health`, `/health/scheduler`, `/health/messenger`.

### Documentation APIs

- Search docs, resolve tooltip fragments, and inspect coverage diagnostics.
- Routes: `/docs/search`, `/docs/fragments/{uiKey}`, `/docs/coverage`, `/docs/diagnostics`.

### Discovery and Interrogation

- Session lifecycle, answer submission, planning, and export actions.
- Prefix: `/interrogation/*`.

### Messenger and Connectors

- Connector CRUD, schema/test actions, and provider webhooks.
- Prefixes: `/messenger/*` and `/connectors/*`.

### Delegation and Org

- Delegation graphs/tasks plus org agents, rituals, councils, escalations, and cost summaries.
- Prefixes: `/delegation/*` and `/org/*`.

### Memory

- Retrieval, core blocks, settings, diagnostics, and working memory views.
- Prefix: `/memory/*`.

## Settings

`api.tokens.default_expiration_days` determines baseline token lifespan for API integrations.

| Setting | Purpose | Typical Value |
| --- | --- | --- |
| `api.tokens.default_expiration_days` | Default token expiry window | `30` |

## Example

When onboarding an integration:

1. Generate a token using the profile API-token flow.
2. Validate with a read endpoint (`GET /agent/api/v1/jobs`).
3. Add write actions with mutation throttling awareness.
4. Add webhook endpoints and signature validation for connector events.

## Troubleshooting

- `401/419`: confirm Sanctum authentication/session token and CSRF boundaries.
- `429`: respect endpoint throttle policies and backoff.
- `503` on docs search: verify search backend availability and reindex jobs.

## Related Docs

- [API Token and Integration Flows](/docs/api-token-integration-flows)
- [Agent API v1 Route Inventory](/docs/agent-api-v1-route-inventory)
