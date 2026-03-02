---
slug: messenger-control-plane
title: Messenger Control Plane
summary: Configure connectors, health, and command routing for messenger integrations.
section: messenger
audience: operator
status: published
version: "1.0.0"
tags:
  - messenger
  - integrations
owner: docs-team
route_names:
  - tools.messenger.index
setting_keys:
  - messenger.health_poll_interval_seconds
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Messenger Control Plane

## Settings

Use `messenger.health_poll_interval_seconds` to set dashboard polling frequency.

## Example

Create a connector, run a test message, and verify health status turns green.

## Troubleshooting

If a connector reports degraded status, inspect webhook signatures and dead-letter queue entries.

