---
slug: requirements-discovery-overview
title: Requirements Discovery Overview
summary: Guided discovery sessions for requirement capture, planning, and build orchestration.
section: discovery
audience: operator
status: published
version: "1.1.0"
tags:
  - discovery
  - planning
  - orchestration
owner: docs-team
route_names:
  - tools.discovery.index
setting_keys:
  - discovery.default_provider
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Requirements Discovery Overview

Requirements Discovery transforms feature intent into approved plans and executable build task sequences.

## Interface Coverage

- **Session list** with phase/progress/status.
- **Question workflow** for requirement clarification.
- **Summary and plan review** with revision support.
- **Build panel** for task execution orchestration.

## Settings

`discovery.default_provider` controls which task provider is preselected for new sessions.

## Configuration Notes

- Sessions rely on queue processing and provider connectivity.
- Build execution metadata tracks blockers (permissions, rate limits, clarification).

## Example

Open a new discovery session, answer generated questions, approve the summary and plan, then start build execution.

## Troubleshooting

- Stalled phase progression: confirm queue workers and provider auth state.
- Repeated duplicate prompts: inspect round repair logic and question history.
- Build tasks marked failed without code changes: inspect execution evidence metadata.

## Related Docs

- [Agent Jobs Overview](/docs/jobs-overview)
- [Org Layer Overview](/docs/org-layer-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.discovery.index` | ok | `tools/discovery` | `GET` |

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `discovery.default_provider` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
