---
slug: memory-diagnostics
title: Memory Diagnostics
summary: Observe memory retrieval quality and operational memory health.
section: memory
audience: operator
status: published
version: "1.0.0"
tags:
  - memory
  - diagnostics
owner: docs-team
route_names:
  - tools.memory.index
setting_keys:
  - memory.retrieval_limit
feature_flags:
  - docs_center_enabled
locale: en
reviewed_at: 2026-03-02
---
# Memory Diagnostics

## Settings

Configure `memory.retrieval_limit` to cap retrieval result size per request.

## Example

Run a retrieval test and compare returned snippets after changing the retrieval limit.

## Troubleshooting

If retrievals return empty data, verify provider credentials and embedding index freshness.

