---
slug: delegation-overview
title: Delegation Overview
summary: Design, validate, and run delegation graphs with controlled verification and retry behavior.
section: delegation
audience: operator
status: published
version: "1.1.0"
tags:
  - delegation
  - orchestration
  - verification
owner: docs-team
route_names:
  - agent.delegation.index
setting_keys:
  - delegation.max_parallel_tasks
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Delegation Overview

Delegation orchestrates multi-step graph execution with dependency ordering, assignment, and verification checkpoints.

## Interface Coverage

- **Graph list** with status and health indicators.
- **Task graph view** with dependencies and verification state.
- **Attempt timeline** for retries and outcomes.

## Settings

`delegation.max_parallel_tasks` defines concurrency bounds for delegated graph execution.

## Example

Create a graph with dependent tasks, assign capabilities, run execution, and inspect verification results per node.

## Capabilities

Delegatee profiles and task contracts use **capability slugs** (e.g. `code_execution`, `browser`, `mcp`) to match which delegatees can run which tasks. The list of available capabilities is **config-driven**:

- **Source:** `config/delegation.php` → `capabilities_seed`. Slugs are seeded into the `delegation_capabilities` table via `DelegationCapabilitySeeder`.
- **API:** `GET /agent/api/v1/delegation/capabilities` returns active capabilities (id, slug, name, description) for the profile form and task contracts.
- **Extending:** Add slugs to `capabilities_seed` in config (or override via env if you expose it), then run `php artisan db:seed --class=DelegationCapabilitySeeder` so new rows are created. Existing rows are left unchanged (`firstOrCreate` by slug).

The default seed set includes OpenClaw-aligned domains (e.g. `browser`, `filesystem`, `research`, `tools`, `mcp`, `runtime`, `web`, `discovery`, `orchestration`) in addition to the original six (`code_execution`, `review`, `testing`, `documentation`, `deployment`, `monitoring`). Runtime execution modes (e.g. in `config/runtime.php`) use a separate capability model for tool policy (read/write/query/browser_* etc.); delegation capabilities are for **task–delegatee matching** only.

## Troubleshooting

- Frequent retry loops: inspect failure mode and verification policy thresholds.
- Tasks stuck pending: verify dependency completion and queue worker health.
- Assignment failures: ensure delegatee capabilities match task requirements.

## Related Docs

- [Requirements Discovery Overview](/docs/requirements-discovery-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.delegation.index` | ok | `agent/delegation` | `GET` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/delegation/capabilities`**
  - Controller: `DelegateeProfileController@capabilities`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/delegation/delegatee-profiles`**
  - Controller: `DelegateeProfileController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/delegation/delegatee-profiles`**
  - Controller: `DelegateeProfileController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/delegation/delegatee-profiles/{id}`**
  - Controller: `DelegateeProfileController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/delegation/delegatee-profiles/{id}`**
  - Controller: `DelegateeProfileController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/delegation/delegatee-profiles/{id}`**
  - Controller: `DelegateeProfileController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/delegation/delegatee-profiles/{id}/restore`**
  - Controller: `DelegateeProfileController@restore`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/delegation/delegatee-profiles/{id}/trust`**
  - Controller: `DelegateeProfileController@trust`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/delegation/graphs`**
  - Controller: `DelegationGraphController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/delegation/graphs`**
  - Controller: `DelegationGraphController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/delegation/graphs/{graphId}/tasks`**
  - Controller: `DelegationTaskController@index`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}`**
  - Controller: `DelegationTaskController@show`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/delegation/graphs/{graphId}/tasks/{taskId}/verification/resolve`**
  - Controller: `DelegationTaskController@resolveVerification`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/delegation/graphs/{id}`**
  - Controller: `DelegationGraphController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/delegation/graphs/{id}`**
  - Controller: `DelegationGraphController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/delegation/graphs/{id}`**
  - Controller: `DelegationGraphController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/delegation/graphs/{id}/cancel`**
  - Controller: `DelegationGraphController@cancel`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/delegation/graphs/{id}/clone`**
  - Controller: `DelegationGraphController@clone`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/delegation/graphs/{id}/events`**
  - Controller: `DelegationGraphController@events`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/delegation/graphs/{id}/restore`**
  - Controller: `DelegationGraphController@restore`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/delegation/graphs/{id}/start`**
  - Controller: `DelegationGraphController@start`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/delegation/graphs/{id}/validate`**
  - Controller: `DelegationGraphController@validate`
  - Auth: `auth:sanctum`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `delegation.max_parallel_tasks` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
