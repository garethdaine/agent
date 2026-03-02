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
