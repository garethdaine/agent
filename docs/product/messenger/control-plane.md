---
slug: messenger-control-plane
title: Messenger Control Plane
summary: Configure connector accounts, health, command routing, and gateway operations.
section: messenger
audience: operator
status: published
version: "1.1.0"
tags:
  - messenger
  - connectors
  - gateway
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

The Messenger control plane manages Slack/Telegram/Discord/WhatsApp integrations and gateway runtime behavior.

## Interface Coverage

- **Connector accounts list** with status and health indicators.
- **Install/setup flows** per provider.
- **Gateway controls** for start/stop/restart and worker diagnostics.

## Settings

`messenger.health_poll_interval_seconds` sets control-plane health refresh interval.

## Configuration Notes

- Webhook providers require signature verification and callback URL consistency.
- Polling providers require credential validation and worker process availability.

## Example

Create a connector, validate credentials, run a provider test, and verify gateway health transitions to `connected`.

## Troubleshooting

- Degraded health with webhook providers often indicates signature mismatch or callback route issues.
- Polling providers stuck disconnected often indicate invalid token or network/firewall restrictions.
- If commands are not routed, verify connector status and provider-specific feature flags.

## Related Docs

- [API Token and Integration Flows](/docs/api-token-integration-flows)
