---
slug: docs-center-overview
title: Docs Center Overview
summary: Navigation, search, and reading behavior for the internal documentation center.
section: docs
audience: operator
status: published
version: "1.0.0"
tags:
  - docs
  - search
  - navigation
owner: docs-team
route_names:
  - docs.index
setting_keys:
  - docs.search.enabled
feature_flags:
  - docs_center_enabled
  - docs_search_enabled
locale: en
reviewed_at: 2026-03-02
---
# Docs Center Overview

The Docs Center provides internal documentation for product surfaces and API contracts, including in-app search and contextual navigation.

## Interface Coverage

- **Left sidebar**: search field, domain filter, section filter, and document list.
- **Right document pane**: full markdown-rendered content for the selected document.
- **Direct routes**: `/docs` for index and `/docs/{slug}` for focused document view.

## Settings

`docs.search.enabled` controls whether docs search is exposed in UI and API integrations.

| Setting | Purpose | Typical Value |
| --- | --- | --- |
| `docs.search.enabled` | Enables docs search interactions | `true` |

## Search Behavior

- Search supports title, summary, slug, and body matching.
- Filters can narrow results by domain (`product_doc`, `api_doc`) and section.
- Search state is preserved in query parameters for shareable URLs.

## Example

1. Open `/docs`.
2. Search for `memory retrieval`.
3. Filter to section `memory`.
4. Open a result and share the URL with teammates.

## Troubleshooting

- If docs list is empty, run `php artisan docs:sync --mode=commit --source=repo`.
- If results are stale, run `php artisan docs:sync --mode=deploy --source=repo`.
- If markdown appears unformatted, confirm the document contains valid markdown body after front matter.

## Related Docs

- [Interface Surface Coverage](/docs/interface-surface-coverage)
- [Agent API v1 Surface Reference](/docs/agent-api-v1-surface-reference)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `docs.index` | ok | `docs` | `GET` |

### Referenced Settings Keys

- `docs.search.enabled`

### Referenced Feature Flags

- `docs_center_enabled`
- `docs_search_enabled`
<!-- AUTO-GENERATED:END -->
