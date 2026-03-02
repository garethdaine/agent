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
