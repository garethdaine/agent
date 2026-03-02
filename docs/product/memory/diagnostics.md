---
slug: memory-diagnostics
title: Memory Diagnostics
summary: Monitor retrieval quality, memory pipeline behavior, and provider health signals.
section: memory
audience: operator
status: published
version: "1.1.0"
tags:
  - memory
  - diagnostics
  - retrieval
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

Memory Diagnostics helps operators evaluate retrieval relevance, provider behavior, and pipeline health.

## Interface Coverage

- **Diagnostics cards** for retrieval latency and success/error counts.
- **Settings editor** for key retrieval controls.
- **Test connection** workflow for provider readiness.

## Settings

`memory.retrieval_limit` caps number of retrieval snippets returned per request.

## Configuration Notes

- Provider credentials and embedding/index availability directly impact retrieval quality.
- Missing context often reflects low-quality source memory, not only retrieval settings.

## Example

Lower retrieval limit to reduce noise, run retrieval tests, then compare answer quality and latency.

## Troubleshooting

- Empty retrievals: verify provider credentials and source memory density.
- High latency: inspect provider round-trip and index health.
- Irrelevant snippets: tune classification and ingestion quality.

## Related Docs

- [Monitor Overview](/docs/monitor-overview)

<!-- AUTO-GENERATED:START -->
## Runtime Contract Snapshot

The block below is generated from code and front-matter metadata.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.memory.index` | ok | `tools/memory` | `GET` |

### Referenced Settings Keys

- `memory.retrieval_limit`

### Referenced Feature Flags

- `docs_center_enabled`
<!-- AUTO-GENERATED:END -->
