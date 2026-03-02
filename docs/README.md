# Documentation Content Contract (Phase 1)

The repository is the source of truth for internal documentation content.

Phase 1 contract scope:
- Product docs live under `docs/product/**`.
- API narrative docs live under `docs/api/**`.
- Tooltip fragments live under `docs/tooltips/**`.
- Locale support is strict English-only (`en`) in this phase.

## Markdown Contract

All markdown files in `docs/product/**` and `docs/api/**` must start with YAML front matter.

Required front matter fields:
- `slug` (kebab-case)
- `title`
- `summary`
- `section`
- `audience`
- `status` (`draft|published|deprecated`)
- `version`
- `tags` (array of strings)
- `owner`
- `route_names` (array of strings)
- `setting_keys` (array of strings)
- `feature_flags` (array of strings)
- `locale` (`en`)
- `reviewed_at` (`YYYY-MM-DD`)

Example:

```markdown
---
slug: dashboard-overview
title: Dashboard Overview
summary: Dashboard purpose and usage.
section: dashboard
audience: operator
status: published
version: "1.0.0"
tags:
  - dashboard
owner: docs-team
route_names:
  - dashboard
setting_keys:
  - dashboard.default_range
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-01
---
# Dashboard
```

## Tooltip YAML Contract

Tooltip files are YAML lists of fragments.

Required fragment fields:
- `ui_key`
- `short_text` (max 120 chars)
- `severity` (`info|warning|risk`)
- `links` (array of `{label, url}`)
- `metadata` object with:
- `owner`
- `locale` (`en`)

Allowed external link domains:
- `laravel.com`
- `typesense.org`
- `scramble.dedoc.co`

Relative links like `/docs/...` are allowed.

Example:

```yaml
- ui_key: dashboard.load_time
  short_text: Shows recent dashboard load-time trend.
  severity: warning
  links:
    - label: Laravel Docs
      url: https://laravel.com/docs/12.x
  metadata:
    owner: docs-team
    locale: en
```

## Validation Command

Run:

```bash
php artisan docs:validate
```

The command validates all files in configured contract directories and runs link/orphan checks:
- unresolved or deprecated `learn_more_slug` references,
- missing required tooltip `ui_key` coverage,
- missing critical route documentation mappings.

## Coverage Gate Command

Run:

```bash
php artisan docs:coverage --fail-on-missing
```

This command enforces strict surface coverage criteria (overview, settings detail, example, troubleshooting, tooltip links) and exits non-zero when any required coverage is missing.
