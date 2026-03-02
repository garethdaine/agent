---
slug: jobs-list-api
title: List Agent Jobs API
summary: Authenticated endpoint contract for listing agent jobs.
section: api-jobs
audience: developer
status: published
version: "1.0.0"
tags:
  - api
  - jobs
owner: docs-team
route_names:
  - agent.jobs.index
setting_keys:
  - jobs.default_page_size
feature_flags:
  - docs_search_enabled
locale: en
reviewed_at: 2026-03-01
---
# List Agent Jobs API

## Settings

Use `jobs.default_page_size` to define the default pagination size for list responses.

## Example

Call `GET /agent/api/v1/jobs?page=1` with a valid auth token to retrieve the first page.

## Troubleshooting

If the API returns 401, verify the token and `auth:sanctum` session state.
