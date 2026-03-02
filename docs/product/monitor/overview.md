---
slug: monitor-overview
title: Monitor Overview
summary: Real-time and historical run-state diagnostics for active and completed runs.
section: monitor
audience: operator
status: published
version: "1.1.0"
tags:
  - monitor
  - run-lifecycle
  - diagnostics
owner: docs-team
route_names:
  - agent.monitor.index
setting_keys:
  - monitor.poll_interval_seconds
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Monitor Overview

Monitor provides run-level observability: lifecycle transitions, stdout/stderr streams, and failure metadata.

## Interface Coverage

- **Run list**: active and recent runs with status and elapsed duration.
- **Run detail panel**: lifecycle timeline, consolidated logs, and structured error notices.
- **Filters**: job, status, trigger type, and time window.

## Settings

`monitor.poll_interval_seconds` defines UI polling cadence when websocket updates are unavailable.

## Configuration Notes

- Streaming quality depends on queue health and event writer throughput.
- Lifecycle events are deduplicated and normalized before display.

## Example

Filter by `failed` status, open the latest failed run, inspect stderr and structured blocker notices, then retry after remediation.

## Troubleshooting

- Missing logs: verify event ingestion and run event persistence.
- Delayed status updates: verify websocket transport or polling fallback.
- Repeated “blocked” notices: inspect permission/rate-limit/clarification metadata.

## Related Docs

- [Dashboard Overview](/docs/dashboard-overview)
- [Agent Jobs Overview](/docs/jobs-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.monitor.index` | ok | `agent/monitor` | `GET` |

### Referenced Settings Keys

- `monitor.poll_interval_seconds`

### Referenced Feature Flags

- `docs_center_enabled`
<!-- AUTO-GENERATED:END -->
