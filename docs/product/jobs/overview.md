---
slug: jobs-overview
title: Agent Jobs Overview
summary: Complete guide for creating, scheduling, validating, editing, and operating agent jobs.
section: jobs
audience: operator
status: published
version: "1.0.0"
tags:
  - jobs
  - scheduling
  - operations
owner: docs-team
route_names:
  - agent.jobs.index
  - agent.jobs.create
  - agent.jobs.edit
setting_keys:
  - jobs.default_page_size
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Agent Jobs Overview

The Jobs area is the primary control plane for scheduled automation. It covers job definition, runner selection, validation, dispatch, and run history.

## Interface Coverage

- **Jobs list**: browse all saved jobs with status badges and next-run visibility.
- **Create job**: define cron schedule, command template, runner type, paths, and environment overrides.
- **Edit job**: update schedule, command placeholders, limits, and run policy.
- **Run actions**: run now, stop, retry, and inspect run events.

## Settings

`jobs.default_page_size` controls list pagination size for the jobs index UI.

| Setting | Purpose | Typical Value |
| --- | --- | --- |
| `jobs.default_page_size` | Default number of rows per jobs page | `25` |

## Validation and Safety

- Job commands are validated against allowlisted executables and placeholder rules.
- Working directory and markdown task paths must be absolute and policy-allowed.
- Environment overrides reject sensitive keys and disallowed patterns.

## Example

1. Create a Codex job with cron `*/15 * * * *`.
2. Set working directory to your project root.
3. Save and trigger run-now.
4. Open the run detail and confirm lifecycle transitions and stdout events.

## Troubleshooting

- If save fails, inspect command placeholder and path policy errors.
- If runs remain queued, verify queue worker health (`horizon`/queue listener).
- If command launches but exits quickly, inspect run events for executable path or permission issues.

## Related Docs

- [Dashboard Overview](/docs/dashboard-overview)
- [Monitor Overview](/docs/monitor-overview)
- [List Jobs API](/docs/jobs-list-api)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.jobs.create` | ok | `agent/jobs/create` | `GET` |
| `agent.jobs.edit` | ok | `agent/jobs/{id}/edit` | `GET` |
| `agent.jobs.index` | ok | `agent/jobs` | `GET` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/jobs`**
  - Controller: `AgentJobController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/jobs`**
  - Controller: `AgentJobController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/jobs/by-workflow/{workflowKey}`**
  - Controller: `AgentJobController@showByWorkflowKey`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/jobs/{id}`**
  - Controller: `AgentJobController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/jobs/{id}`**
  - Controller: `AgentJobController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/jobs/{id}`**
  - Controller: `AgentJobController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/jobs/{id}/restore`**
  - Controller: `AgentJobController@restore`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/jobs/{id}/run-now`**
  - Controller: `AgentJobController@runNow`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/jobs/{id}/runs`**
  - Controller: `AgentJobController@runs`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/jobs/{id}/toggle`**
  - Controller: `AgentJobController@toggle`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `jobs.default_page_size` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
