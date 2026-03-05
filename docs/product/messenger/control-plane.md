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

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.messenger.index` | ok | `tools/messenger` | `GET` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/messenger/connectors`**
  - Controller: `MessengerConnectorController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/messenger/connectors`**
  - Controller: `MessengerConnectorController@store`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/messenger/connectors/schema`**
  - Controller: `MessengerConnectorController@schema`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/messenger/connectors/{id}`**
  - Controller: `MessengerConnectorController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/messenger/connectors/{id}`**
  - Controller: `MessengerConnectorController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`DELETE agent/api/v1/messenger/connectors/{id}`**
  - Controller: `MessengerConnectorController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/messenger/connectors/{id}/policy`**
  - Controller: `ConnectorPolicyController@show`
  - Auth: `auth:sanctum`
- **`PUT agent/api/v1/messenger/connectors/{id}/policy`**
  - Controller: `ConnectorPolicyController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET,PUT agent/api/v1/messenger/connectors/{id}/soul`**
  - Controller: `MessengerConnectorController@soul`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/messenger/connectors/{id}/test`**
  - Controller: `MessengerConnectorController@test`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`GET agent/api/v1/messenger/metrics`**
  - Controller: `MessengerMetricsController@index`
  - Auth: `auth:sanctum`
- **`GET agent/api/v1/messenger/pairings`**
  - Controller: `PairingController@index`
  - Auth: `auth:sanctum`
- **`POST agent/api/v1/messenger/pairings/{id}/approve`**
  - Controller: `PairingController@approve`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`
- **`POST agent/api/v1/messenger/pairings/{id}/revoke`**
  - Controller: `PairingController@revoke`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:agent-mutations`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `messenger.health_poll_interval_seconds` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
