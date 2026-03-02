---
slug: backups-settings
title: Backup Settings
summary: Configure backup retention, schedule, and execution behavior.
section: backups
audience: operator
status: published
version: "1.0.0"
tags:
  - backups
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

## Settings

Configure `backups.retention_days` to define how long historical archives are preserved.

## Example

Set retention to 30 days and trigger a manual backup run to confirm policy behavior.

## Troubleshooting

If backups fail, verify storage permissions and inspect backup job error output.

