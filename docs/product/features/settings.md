---
slug: feature-flags-settings
title: Feature Flag Settings
summary: Manage runtime feature toggles and safe rollout behavior.
section: features
audience: operator
status: published
version: "1.0.0"
tags:
  - feature-flags
  - rollout
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

## Settings

Use `features.flags_refresh_minutes` to configure refresh interval for cached flag values.

## Example

Enable a non-critical flag in staging first, then promote to production after validation.

## Troubleshooting

If toggles appear stale, clear cache and confirm feature settings API writes are successful.

