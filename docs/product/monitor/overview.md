---
slug: monitor-overview
title: Monitor Overview
summary: Run-state monitoring and operational diagnostics.
section: monitor
audience: operator
status: published
version: "1.0.0"
tags:
  - monitor
  - operations
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

## Settings

Configure `monitor.poll_interval_seconds` to tune refresh cadence for active runs.

## Example

Filter monitor rows by run state to isolate long-running or failed executions.

## Troubleshooting

If state changes do not stream, verify websocket connectivity and queue consumers.
