---
slug: org-layer-overview
title: Org Layer Overview
summary: Manage org-level agents, rituals, councils, and escalation workflows.
section: org
audience: operator
status: published
version: "1.1.0"
tags:
  - org
  - governance
  - agents
owner: docs-team
route_names:
  - org.index
setting_keys:
  - org.default_execution_window_minutes
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Org Layer Overview

The Org layer governs multi-agent organizational workflows, recurring rituals, councils, and escalation handling.

## Interface Coverage

- **Agents page** for profile and capability management.
- **Rituals page** for recurring operational workflows.
- **Councils page** for structured decision workflows.
- **Escalations page** for incident resolution and approvals.

## Settings

`org.default_execution_window_minutes` sets expected runtime window for ritual/council executions.

## Example

Create a ritual template, schedule a run, monitor progression, and resolve any generated escalation.

## Troubleshooting

- Missing runs: verify schedule worker and org feature gates.
- Escalations not updating: validate user permissions and route middleware.
- Slow org pages: inspect relational query load and pagination settings.

## Related Docs

- [Delegation Overview](/docs/delegation-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `org.index` | ok | `agent/org` | `GET` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/org/agents`**
  - Controller: `OrgAgentController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/org/agents`**
  - Controller: `OrgAgentController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/agents/{id}`**
  - Controller: `OrgAgentController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/org/agents/{id}`**
  - Controller: `OrgAgentController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/org/agents/{id}`**
  - Controller: `OrgAgentController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/org/agents/{id}/restore`**
  - Controller: `OrgAgentController@restore`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/costs/summary`**
  - Controller: `OrgCostController@summary`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/org/councils`**
  - Controller: `OrgCouncilController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/org/councils`**
  - Controller: `OrgCouncilController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/councils/{id}`**
  - Controller: `OrgCouncilController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/org/councils/{id}`**
  - Controller: `OrgCouncilController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/org/councils/{id}`**
  - Controller: `OrgCouncilController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/escalations`**
  - Controller: `OrgEscalationController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/org/escalations/{id}/resolve`**
  - Controller: `OrgEscalationController@resolve`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/ritual-runs`**
  - Controller: `OrgRitualRunController@index`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/org/ritual-runs/{id}`**
  - Controller: `OrgRitualRunController@show`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/org/ritual-runs/{id}/retry`**
  - Controller: `OrgRitualRunController@retry`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/rituals`**
  - Controller: `OrgRitualController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/org/rituals`**
  - Controller: `OrgRitualController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/org/rituals/{id}`**
  - Controller: `OrgRitualController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/org/rituals/{id}`**
  - Controller: `OrgRitualController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/org/rituals/{id}`**
  - Controller: `OrgRitualController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/org/rituals/{id}/pause`**
  - Controller: `OrgRitualController@pause`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/org/rituals/{id}/restore`**
  - Controller: `OrgRitualController@restore`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/org/rituals/{id}/resume`**
  - Controller: `OrgRitualController@resume`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/org/rituals/{id}/run`**
  - Controller: `OrgRitualController@run`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `org.default_execution_window_minutes` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
