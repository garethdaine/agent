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
reviewed_at: 2026-03-03
---
# Interface Surface Coverage

This page is generated from `config/docs_coverage.php`, route registration, and docs metadata.

## Settings

Coverage requires each surface to retain linked docs, route bindings, setting references, and tooltip linkage.

## Product Navigation Coverage

| Surface | Required Routes | Settings Keys | Linked Doc | Route Status |
| --- | --- | --- | --- | --- |
| Dashboard | `dashboard` | `dashboard.default_range` | [Dashboard Overview](/docs/dashboard-overview) | ok |
| Agent Jobs | `agent.jobs.index` | `jobs.default_page_size` | [List Agent Jobs API](/docs/jobs-list-api) | ok |
| Monitor | `agent.monitor.index` | `monitor.poll_interval_seconds` | [Monitor Overview](/docs/monitor-overview) | ok |
| Messenger Control Plane | `tools.messenger.index` | `messenger.health_poll_interval_seconds` | [Messenger Control Plane](/docs/messenger-control-plane) | ok |
| Requirements Discovery | `tools.discovery.index` | `discovery.default_provider` | [Requirements Discovery Overview](/docs/requirements-discovery-overview) | ok |
| Backups | `tools.backups.settings` | `backups.retention_days` | [Backup Settings](/docs/backups-settings) | ok |
| Feature Flags | `tools.features.settings` | `features.flags_refresh_minutes` | [Feature Flag Settings](/docs/feature-flags-settings) | ok |
| Memory Diagnostics | `tools.memory.index` | `memory.retrieval_limit` | [Memory Diagnostics](/docs/memory-diagnostics) | ok |
| Delegation | `agent.delegation.index` | `delegation.max_parallel_tasks` | [Delegation Overview](/docs/delegation-overview) | ok |
| Org Layer | `org.index` | `org.default_execution_window_minutes` | [Org Layer Overview](/docs/org-layer-overview) | ok |
| Profile Security Account | `profile.show` | `profile.session_timeout_minutes` | [Profile Security and Account](/docs/profile-security-account) | ok |
| API Token Integration Flows | `messenger.link.show` | `api.tokens.default_expiration_days` | [API Token and Integration Flows](/docs/api-token-integration-flows) | ok |

## Example

After adding a new route to a covered surface, run `php artisan docs:generate` and confirm this table reflects the new binding.

## Troubleshooting

- If a linked doc is missing, ensure front matter includes the relevant `route_names` and `setting_keys`.
- If route status is `partial`, verify route names in docs front matter match `routes/web.php` or `routes/api.php`.
- Re-run `php artisan docs:coverage --fail-on-missing` to identify strict gate failures.

## Related Docs

- [Docs Center Overview](/docs/docs-center-overview)
- [Agent API v1 Surface Reference](/docs/agent-api-v1-surface-reference)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `agent.delegation.index` | ok | `agent/delegation` | `GET` |
| `agent.jobs.index` | ok | `agent/jobs` | `GET` |
| `agent.monitor.index` | ok | `agent/monitor` | `GET` |
| `dashboard` | ok | `dashboard` | `GET` |
| `docs.index` | ok | `docs` | `GET` |
| `org.index` | ok | `agent/org` | `GET` |
| `profile.show` | ok | `user/profile` | `GET` |
| `tools.backups.settings` | ok | `tools/backups/settings` | `GET` |
| `tools.discovery.index` | ok | `tools/discovery` | `GET` |
| `tools.features.settings` | ok | `tools/features/settings` | `GET` |
| `tools.memory.index` | ok | `tools/memory` | `GET` |
| `tools.messenger.index` | ok | `tools/messenger` | `GET` |

### Referenced Settings Keys

- `api.tokens.default_expiration_days`
- `backups.retention_days`
- `dashboard.default_range`
- `delegation.max_parallel_tasks`
- `discovery.default_provider`
- `features.flags_refresh_minutes`
- `jobs.default_page_size`
- `memory.retrieval_limit`
- `messenger.health_poll_interval_seconds`
- `monitor.poll_interval_seconds`
- `org.default_execution_window_minutes`
- `profile.session_timeout_minutes`

### Referenced Feature Flags

- `docs_center_enabled`
- `docs_search_enabled`
- `help_hint_enabled`
<!-- AUTO-GENERATED:END -->
