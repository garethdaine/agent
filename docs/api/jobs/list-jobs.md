---
slug: jobs-list-api
title: List Agent Jobs API
summary: Contract for listing jobs, pagination, filtering, and response expectations.
section: api-jobs
audience: developer
status: published
version: "1.1.0"
tags:
  - api
  - jobs
  - pagination
owner: docs-team
route_names:
  - agent.jobs.index
setting_keys:
  - jobs.default_page_size
feature_flags:
  - docs_search_enabled
locale: en
reviewed_at: 2026-03-02
---
# List Agent Jobs API

## Endpoint

`GET /agent/api/v1/jobs`

## Authentication

Requires authenticated session or token accepted by API middleware.

## Query Parameters

| Parameter | Type | Description |
| --- | --- | --- |
| `page` | integer | Page number (1-based) |
| `per_page` | integer | Page size override (bounded) |
| `search` | string | Title/name filter |
| `is_enabled` | boolean | Filter enabled/disabled jobs |

## Settings

`jobs.default_page_size` sets default `per_page` value when omitted.

## Response Shape

```json
{
  "data": [
    {
      "id": 12,
      "name": "Daily docs sync",
      "runner_type": "codex",
      "is_enabled": true
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 87
  }
}
```

## Example

Call `GET /agent/api/v1/jobs?page=1&per_page=25` to retrieve first page with explicit size.

## Troubleshooting

- `401 Unauthorized`: verify auth session/token.
- Empty results with expected data: validate filter params.
- Validation errors (`422`): check `per_page` bounds and parameter types.

## Related Docs

- [Agent Jobs Overview](/docs/jobs-overview)
