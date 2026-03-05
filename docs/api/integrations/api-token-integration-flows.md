---
slug: api-token-integration-flows
title: API Token and Integration Flows
summary: Token lifecycle, account-link behavior, and integration guardrails.
section: api-integrations
audience: developer
status: published
version: "1.1.0"
tags:
  - api
  - tokens
  - integrations
owner: docs-team
route_names:
  - messenger.link.show
setting_keys:
  - api.tokens.default_expiration_days
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# API Token and Integration Flows

## Overview

This guide covers token issuance and usage patterns used by authenticated API and account-link integration flows.

## Token Lifecycle

1. Generate token with scoped permissions.
2. Store securely and rotate on a defined cadence.
3. Revoke compromised or unused tokens.

## Settings

`api.tokens.default_expiration_days` defines default token TTL when a custom expiration is not supplied.

## Integration Flow

- Start account-link flow from authenticated UI.
- Receive callback with signed/validated payload.
- Exchange/verify token and persist linkage state.

## Security Notes

- Never expose raw tokens in client logs.
- Enforce strict callback origin and signature checks.
- Use shortest practical token lifetime for integration actions.

## Example

Create a token, call a protected endpoint, and complete account-link callback verification in one test workflow.

## Troubleshooting

- `401` on protected call: token missing/expired/invalid scope.
- Callback rejected: mismatch in signing secret or redirect URI.
- Link state not persisted: inspect transaction errors and event logs.

## Related Docs

- [Messenger Control Plane](/docs/messenger-control-plane)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `messenger.link.show` | ok | `messenger/link/{token}` | `GET` |

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
| `api.tokens.default_expiration_days` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
