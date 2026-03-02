---
slug: backups-settings
title: Backup Settings
summary: Configure backup policy, retention windows, and on-demand execution for platform resilience.
section: backups
audience: operator
status: published
version: "1.1.0"
tags:
  - backups
  - resilience
  - operations
owner: docs-team
route_names:
  - tools.backups.settings
setting_keys:
  - backups.retention_days
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Backup Settings

Backup settings define how frequently data snapshots are created and how long artifacts are retained.

## Interface Coverage

- **Policy form**: retention, include/exclude domains, execution preferences.
- **Run now** action for immediate backup verification.
- **Status output** with latest run result and failure details.

## Settings

`backups.retention_days` controls retention horizon for backup artifacts.

## Example

Set retention to 30 days, trigger `Run now`, and verify successful artifact creation plus policy compliance.

## Troubleshooting

- Backup run failures typically indicate storage permission or connectivity issues.
- Missing artifacts often indicate path misconfiguration or cleanup policy conflicts.
- Slow backups may indicate oversized domains or I/O saturation.

## Related Docs

- [Org Layer Overview](/docs/org-layer-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.backups.settings` | ok | `tools/backups/settings` | `GET` |

### Referenced Settings Keys

- `backups.retention_days`

### Referenced Feature Flags

- `docs_center_enabled`
<!-- AUTO-GENERATED:END -->
