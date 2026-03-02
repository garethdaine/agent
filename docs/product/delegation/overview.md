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

## Troubleshooting

- Frequent retry loops: inspect failure mode and verification policy thresholds.
- Tasks stuck pending: verify dependency completion and queue worker health.
- Assignment failures: ensure delegatee capabilities match task requirements.

## Related Docs

- [Requirements Discovery Overview](/docs/requirements-discovery-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.delegation.index` | ok | `agent/delegation` | `GET` |

### Referenced Settings Keys

- `delegation.max_parallel_tasks`

### Referenced Feature Flags

- `docs_center_enabled`
<!-- AUTO-GENERATED:END -->
