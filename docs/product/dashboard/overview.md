---
slug: dashboard-overview
title: Dashboard Overview
summary: Dashboard purpose, operating model, and navigation.
section: dashboard
audience: operator
status: published
version: "1.0.0"
tags:
  - dashboard
  - operations
owner: docs-team
route_names:
  - dashboard
setting_keys:
  - dashboard.default_range
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-01
---
# Dashboard Overview

## Settings

Set `dashboard.default_range` to control the default time window for cards and charts.

## Example

Select a 24-hour range to compare failure spikes with the previous day baseline.

## Troubleshooting

If dashboard metrics are stale, confirm queue workers and scheduler heartbeat are healthy.
