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

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `messenger.link.show` | ok | `messenger/link/{token}` | `GET` |

### Referenced Settings Keys

- `api.tokens.default_expiration_days`

### Referenced Feature Flags

- `docs_center_enabled`
<!-- AUTO-GENERATED:END -->
