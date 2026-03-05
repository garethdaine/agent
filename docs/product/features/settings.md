---
slug: feature-flags-settings
title: Feature Flag Settings
summary: Operate runtime feature toggles safely with rollout controls and validation paths.
section: features
audience: operator
status: published
version: "1.1.0"
tags:
  - feature-flags
  - rollout
  - control-plane
owner: docs-team
route_names:
  - tools.features.settings
setting_keys:
  - features.flags_refresh_minutes
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Feature Flag Settings

Feature flags allow staged release, quick rollback, and controlled exposure by route/surface.

## Interface Coverage

- **Flags list** with enabled state and metadata.
- **Toggle interactions** for safe state changes.
- **Audit-oriented feedback** for runtime updates.

## Settings

`features.flags_refresh_minutes` controls cache refresh cadence for flag values.

## Example

Enable a non-critical feature in staging, validate target surface behavior, then enable in production.

## Troubleshooting

- If toggles appear stale, clear cache and confirm write persistence.
- If UI does not react, verify feature gate checks in target controller/component.
- If rollout causes regressions, disable flag and review affected route logs.

## Related Docs

- [Requirements Discovery Overview](/docs/requirements-discovery-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.features.settings` | ok | `tools/features/settings` | `GET` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/features/settings`**
  - Controller: `AgentFeatureSettingsController@index`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/features/settings`**
  - Controller: `AgentFeatureSettingsController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `features.flags_refresh_minutes` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
