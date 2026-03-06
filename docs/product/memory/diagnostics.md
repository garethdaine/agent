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

> This section is auto-generated from code and front-matter metadata. Do not edit manually.

### Verified Route Bindings

| Route Name | Status | URI | Methods |
| --- | --- | --- | --- |
| `tools.memory.index` | ok | `tools/memory` | `GET` |

### API Endpoints

The following API endpoints are available for this feature:

- **`GET agent/api/v1/memory/core-blocks`**
  - Controller: `MemoryCoreBlockController@index`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`GET agent/api/v1/memory/core-blocks/{key}`**
  - Controller: `MemoryCoreBlockController@show`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`PUT agent/api/v1/memory/core-blocks/{key}`**
  - Controller: `MemoryCoreBlockController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-writes`
- **`DELETE agent/api/v1/memory/core-blocks/{key}`**
  - Controller: `MemoryCoreBlockController@destroy`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-writes`
- **`GET agent/api/v1/memory/models`**
  - Controller: `MemoryModelsController`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`POST agent/api/v1/memory/retrieve`**
  - Controller: `MemoryRetrievalController@retrieve`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`GET agent/api/v1/memory/settings`**
  - Controller: `MemorySettingsController@index`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`PUT agent/api/v1/memory/settings`**
  - Controller: `MemorySettingsController@update`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-writes`
- **`GET agent/api/v1/memory/settings/capabilities`**
  - Controller: `MemorySettingsController@capabilities`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`POST agent/api/v1/memory/settings/test-connection`**
  - Controller: `MemorySettingsController@testConnection`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-writes`
- **`GET agent/api/v1/memory/stats`**
  - Controller: `MemoryDiagnosticsController@stats`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`
- **`POST agent/api/v1/memory/working/append`**
  - Controller: `MemoryWorkingController@append`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-writes`
- **`GET agent/api/v1/memory/working/{runId}`**
  - Controller: `MemoryWorkingController@show`
  - Auth: `auth:sanctum`
  - Rate limit: `throttle:memory-reads`

### Configuration Reference

| Setting Key | Current Value | Source |
| --- | --- | --- |
| `memory.retrieval_limit` | _not set_ | _default_ |

### Feature Flags

- `docs_center_enabled`

<!-- AUTO-GENERATED:END -->
