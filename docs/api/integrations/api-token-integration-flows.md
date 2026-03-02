---
slug: api-token-integration-flows
title: API Token and Integration Flows
summary: Authenticated token usage and account-link integration flow reference.
section: api-integrations
audience: developer
status: published
version: "1.0.0"
tags:
  - api
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

## Settings

Use `api.tokens.default_expiration_days` to define default token lifetime policy.

## Example

Generate a token, authenticate an API request, and complete the messenger account-link flow.

## Troubleshooting

If linking fails, validate token signature, expiration, and callback route configuration.

