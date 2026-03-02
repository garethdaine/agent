---
slug: dashboard-overview
title: Dashboard Overview
summary: Operational command center for run health, queue status, failure trends, and system heartbeat visibility.
section: dashboard
audience: operator
status: published
version: "1.1.0"
tags:
  - dashboard
  - operations
  - observability
owner: docs-team
route_names:
  - dashboard
setting_keys:
  - dashboard.default_range
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Dashboard Overview

The Dashboard is the first-stop operational view for authenticated users. It summarizes scheduler health, run outcomes, and near-real-time system activity.

## Interface Coverage

- **Top KPI cards**: run success/failure snapshots, throughput trend, queue health indicators.
- **Activity graph**: recent run volume over selected time window.
- **Status widgets**: scheduler heartbeat, messenger health, and downstream dependency status.
- **Quick links**: jump points into Jobs, Monitor, Messenger, and Docs.

## Settings

`dashboard.default_range` controls the default time span used by cards and charts.

| Setting | Purpose | Typical Value |
| --- | --- | --- |
| `dashboard.default_range` | Initial date range at page load | `24h` |

## Configuration Notes

- Dashboard data relies on queue workers and scheduler writes being healthy.
- If websocket transport is unavailable, dashboard tiles can appear stale until polling catches up.

## User Flows

1. Open Dashboard.
2. Validate scheduler and queue indicators are green.
3. Drill into an anomaly (for example, failure spike) by navigating to Monitor or Jobs.

## Example

Use the `24h` default range to compare current failure rate against the previous business day after a deployment.

## Troubleshooting

- If metrics are stale, confirm `horizon` and `schedule:work` are running.
- If cards show zeros unexpectedly, verify recent `agent_job_runs` writes and cache health.
- If charts fail to paint, check browser console/network responses for dashboard metrics endpoints.

## Related Docs

- [Monitor Overview](/docs/monitor-overview)
- [Agent Jobs Overview](/docs/jobs-overview)
