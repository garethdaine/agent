---
slug: profile-security-account
title: Profile Security and Account
summary: Account profile, session controls, and security workflow guidance.
section: profile
audience: operator
status: published
version: "1.0.0"
tags:
  - profile
  - security
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

## Settings

Set `profile.session_timeout_minutes` to enforce inactive session expiration policy.

## Example

Update profile details, rotate credentials, and confirm session policy takes effect.

## Troubleshooting

If account updates do not persist, verify CSRF/session state and server-side validation errors.

