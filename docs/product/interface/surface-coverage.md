---
slug: interface-surface-coverage
title: Interface Surface Coverage
summary: Master index of every major authenticated interface surface, what users can do, and where to find detailed docs.
section: interface
audience: operator
status: published
version: "1.0.0"
tags:
  - interface
  - navigation
  - coverage
owner: docs-team
route_names:
  - dashboard
  - docs.index
  - agent.jobs.index
  - agent.monitor.index
  - tools.messenger.index
  - tools.discovery.index
  - tools.backups.settings
  - tools.features.settings
  - tools.memory.index
  - agent.delegation.index
  - org.index
  - profile.show
setting_keys:
  - dashboard.default_range
  - jobs.default_page_size
  - monitor.poll_interval_seconds
  - messenger.health_poll_interval_seconds
  - discovery.default_provider
  - backups.retention_days
  - features.flags_refresh_minutes
  - memory.retrieval_limit
  - delegation.max_parallel_tasks
  - org.default_execution_window_minutes
  - profile.session_timeout_minutes
  - api.tokens.default_expiration_days
feature_flags:
  - docs_center_enabled
  - docs_search_enabled
  - help_hint_enabled
locale: en
reviewed_at: 2026-03-02
---
# Interface Surface Coverage

This page tracks all major authenticated UI surfaces and the documentation page that covers each area.

## Product Navigation Coverage

| Navigation Surface | Primary User Actions | Detailed Doc |
| --- | --- | --- |
| Dashboard | Watch system health, run metrics, queue pulse, and quick links | [Dashboard Overview](/docs/dashboard-overview) |
| Docs | Search, filter, and read internal product/API docs | [Docs Center Overview](/docs/docs-center-overview) |
| Jobs | Create, edit, schedule, run, retry, and stop jobs | [Agent Jobs Overview](/docs/jobs-overview) |
| Monitor | Inspect live runs, lifecycle events, and failures | [Monitor Overview](/docs/monitor-overview) |
| Messenger | Manage connectors and health telemetry | [Messenger Control Plane](/docs/messenger-control-plane) |
| Tools: Discovery | Drive requirements sessions and plan output | [Requirements Discovery Overview](/docs/requirements-discovery-overview) |
| Tools: Backups | Configure retention policy and run now | [Backups Settings](/docs/backups-settings) |
| Tools: Feature Flags | Review and toggle feature controls | [Feature Flags Settings](/docs/feature-flags-settings) |
| Tools: Memory | Validate retrieval quality and memory diagnostics | [Memory Diagnostics](/docs/memory-diagnostics) |
| Delegation | Build/execute delegation graphs and tasks | [Delegation Overview](/docs/delegation-overview) |
| Agents (Org) | Manage org agents, rituals, councils, and escalation workflows | [Org Layer Overview](/docs/org-layer-overview) |
| Profile/Security | Manage identity, account profile, and session security | [Profile Security Account](/docs/profile-security-account) |

## Cross-Cutting User Interactions

- **Helper tooltips**: context hints and Learn More links resolve via docs fragments (`/agent/api/v1/docs/fragments/{uiKey}`).
- **Search and routing context**: docs filters persist in URL query so support teams can share exact views.
- **Settings references**: each product doc links relevant setting keys and operational impact.

## Example

Use this page as the first stop when onboarding operators: start with a user’s navigation surface, then jump to the linked deep-dive doc.

## Troubleshooting

- If any table link returns 404, run docs sync and verify the slug exists in `documentation_entries`.
- If tooltip Learn More opens no page, verify its `learn_more_slug` exists and is `published`.
- If a surface is missing, add a product markdown file and re-run docs sync.

## Related Docs

- [Agent API v1 Surface Reference](/docs/agent-api-v1-surface-reference)
- [API Token and Integration Flows](/docs/api-token-integration-flows)
