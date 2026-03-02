---
slug: profile-security-account
title: Profile Security and Account
summary: Manage profile details, credentials, session safety, and account security controls.
section: profile
audience: operator
status: published
version: "1.1.0"
tags:
  - profile
  - security
  - account
owner: docs-team
route_names:
  - profile.show
setting_keys:
  - profile.session_timeout_minutes
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Profile Security and Account

Profile and security settings control user identity data, credential updates, and session policies.

## Interface Coverage

- **Profile form** for name/email/account metadata.
- **Password flow** for credential rotation.
- **Session controls** for timeout and active-session safety.

## Settings

`profile.session_timeout_minutes` defines inactive session expiration duration.

## Example

Update profile metadata, rotate password, then verify active sessions follow timeout policy.

## Troubleshooting

- Profile save failures: inspect validation errors and CSRF/session health.
- Password update issues: confirm current password and policy constraints.
- Unexpected sign-outs: verify timeout setting and session storage behavior.

## Related Docs

- [API Token and Integration Flows](/docs/api-token-integration-flows)
