# Implementation Plan

Derived from discovery session 3.

# Implementation Plan: Four-Layer Memory Architecture

## Summary
Phased implementation of Layers 1–3 of the memory architecture into the existing Laravel 12 / PHP 8.3 Agent Ops codebase. The plan is organized into 8 sequential phases with ~45 implementation tasks. Each phase builds on the prior one and is independently testable. Layer 4 (Delegation Memory) is deferred to v2.

---

## Phase 1: Foundation — Config, Migrations, Models, Provider Registration
**Prerequisite: None | Risk: Low**

### 1.1 Create `config/memory.php`
- Full configuration file as specified in the requirements (providers, working_memory, retrieval, forgetting, consolidation, formation, neo4j, context_injection, rate_limiting, security sections)
- All values env-driven with sensible defaults; `MEMORY_ENABLED=false` by default
- **File**: `config/memory.php` (new)

### 1.2 Database Migrations (8 migrations)
Create migrations in order with timestamp prefix `2026_02_17_0xxxxx`:
1. `enable_pgvector_extension` — `CREATE EXTENSION IF NOT EXISTS vector` wrapped in try/catch; idempotent; logs warning if unavailable. **Note**: pgvector is currently NOT installed on the target database (`vector_installed=no`); this migration must be non-fatal and the system must degrade gracefully if the extension remains unavailable.
2. `create_memory_settings_table` — user_id nullable FK, key varchar(100), value text (encrypted cast), unique(user_id, key)
3. `create_memory_core_blocks_table` — user_id FK, job_id nullable FK, block_type, block_key, content_text/content_json, classification, version; unique(user_id, block_key, job_id)
4. `create_memory_embeddings_table` — vector(1536) nullable, HNSW index conditional on pgvector, GIN tsvector, importance_score, access_count, content_hash
5. `create_memory_conversation_logs_table` — run_id FK, job_id FK, role, content, sequence, event_type, classification; GIN tsvector
6. `create_memory_consolidation_log_table` — consolidation_type, source_count, result_summary, checkpoint_json
7. `create_memory_formation_failures_table` — run_id FK, failure_type, attempts, backfilled_at nullable, payload_json; partial index WHERE backfilled_at IS NULL
8. `create_memory_provider_usage_table` — provider, model, operation, input_tokens, output_tokens, cost_estimate_usd
- **Files**: `database/migrations/2026_02_17_0*.php` (8 new files)
- **Note**: pgvector HNSW index migration must handle the case where extension is not available (wrap in raw SQL try/catch or use Schema::hasExtension check if available)

### 1.3 Eloquent Models (7 models)
- `MemorySetting` — encrypted `value` cast, `setForUser`/`getForUser` static helpers (following `InterrogationSetting` pattern), scopeForUser, scopeWorkspaceDefault
- `MemoryCoreBlock` — version auto-increment on save via model event, `content_json` cast to array, classification enum validation
- `MemoryEmbedding` — `embedding` cast (custom pgvector cast or raw), `metadata_json` array cast, `content_hash` auto-generation via creating event
- `MemoryConversationLog` — immutable (no updated_at), relationship to AgentJobRun/AgentJob
- `MemoryConsolidationLog` — `checkpoint_json` array cast
- `MemoryFormationFailure` — `payload_json` array cast, scope `unbackfilled()`
- `MemoryProviderUsage` — immutable, relationship to user/run
- **Files**: `app/Models/Memory*.php` (7 new files)

### 1.4 MemoryServiceProvider
- Register in `bootstrap/providers.php`
- Gate all bindings behind `config('memory.enabled')`
- Bind contracts (EmbeddingProvider, ExtractionProvider) as singletons via MemoryAdapterFactory (Phase 3)
- Register Neo4j client singleton (Phase 5)
- Conditionally register memory API routes (Phase 7)
- Register scheduled commands (Phase 6)
- **Files**: `app/Providers/MemoryServiceProvider.php` (new), `bootstrap/providers.php` (modify — add entry)

### 1.5 Redis Memory Connection
- Add `memory` connection to `config/database.php` redis section: DB 2, same host/auth as default
- **File**: `config/database.php` (modify — add after line 179)

### 1.6 Horizon `supervisor-memory` Queue
- Add `supervisor-memory` to `config/horizon.php` defaults (after line 228): connection redis, queue `['memory']`, balance auto, maxProcesses env-driven (`HORIZON_MEMORY_MAX_PROCESSES` default 3), timeout 300, tries 1
- Add `'redis:memory' => 30` to waits array
- Add environment overrides for production/local
- **File**: `config/horizon.php` (modify)

### Phase 1 Tests
- Migration rollback/rollforward test
- Model factory + basic CRUD assertions for each model
- MemorySetting encryption round-trip test
- Config loading assertions
- Provider registration gating test (enabled=false → no bindings)

---

## Phase 2: Core Memory (Layer 1) — CoreMemoryManager + Settings
**Prerequisite: Phase 1 | Risk: Low**

### 2.1 MemorySettingsService
- Read/write settings with user_id scoping (NULL = workspace default, user_id = override)
- Encrypted storage for provider keys via Laravel `encrypted` cast
- Key masking helper: show last 4 chars only (`sk-...a1b2`)
- Validation: known keys whitelist, provider name validation
- Capability detection: inspect configured providers, determine if embeddings/extraction available
- **File**: `app/Support/Memory/MemorySettingsService.php` (new)

### 2.2 MemoryCapabilityResolver
- Inspects Memory Settings + pgvector extension availability (via `SELECT 1 FROM pg_extension WHERE extname='vector'`)
- Returns mode enum: `no-api`, `api`, `degraded` (Anthropic-only — text but no embeddings)
- Caches result per request lifecycle (singleton)
- **File**: `app/Support/Memory/MemoryCapabilityResolver.php` (new)

### 2.3 CoreMemoryManager
- CRUD for 5 block keys: `agent_persona`, `user_profile`, `task_state`, `tool_results_cache`, `active_goals`
- Identity blocks (agent_persona, user_profile): freeform text in `content_text`
- Operational blocks (task_state, tool_results_cache, active_goals): JSON schema validation on write via `content_json`
- Version increment on every update (optimistic locking via version check)
- Classification tagging (default `internal`)
- AuditLogger integration: record create/update/delete via `recordSystemAction()` with target_type `memory_core_block`
- User-scoped queries with classification filtering
- **File**: `app/Support/Memory/CoreMemoryManager.php` (new)

### 2.4 JSON Schema Definitions for Operational Blocks
- Define validation schemas for task_state, tool_results_cache, active_goals
- Implement as simple PHP validator (no external JSON Schema lib needed for v1)
- **File**: `app/Support/Memory/Validation/CoreBlockSchemaValidator.php` (new)

### Phase 2 Tests
- CoreMemoryManager: CRUD lifecycle, version increment, classification filtering
- JSON schema validation: valid/invalid payloads for each operational block type
- MemorySettingsService: encryption round-trip, masking, workspace vs user scoping
- MemoryCapabilityResolver: mock pgvector present/absent, mock provider settings for each mode

---

## Phase 3: Provider Abstraction — Contracts, Adapters, Rate Limiting
**Prerequisite: Phase 2 | Risk: Medium (external dependency: laravel/ai)**

### 3.1 Install Dependencies
- `composer require laravel/ai` — Laravel AI SDK for multi-provider abstraction
- `composer require laudis/neo4j-php-client` — Neo4j Bolt protocol client
- **File**: `composer.json` (modify)

### 3.2 Provider Contracts
- `EmbeddingProvider` interface: `embed(string): array`, `embedBatch(array): array`, `dimensions(): int`
- `ExtractionProvider` interface: `extractEntities(string): array`, `scoreImportance(string): float`, `summarize(string, int): string`
- **Files**: `app/Support/Memory/Contracts/EmbeddingProvider.php`, `app/Support/Memory/Contracts/ExtractionProvider.php` (2 new)

### 3.3 MemoryAdapterFactory
- Follows `AdapterFactory::make()` pattern from Interrogation subsystem
- Resolves provider implementations based on Memory Settings (extraction_provider, embeddings_provider, summarization_provider)
- Falls through failover chain on provider unavailability
- Integrates Redis token-bucket rate limiter per provider+model key
- **File**: `app/Support/Memory/MemoryAdapterFactory.php` (new)

### 3.4 Provider Adapters (OpenAI + Anthropic)
- `OpenAiEmbeddingAdapter` implements `EmbeddingProvider` — wraps Laravel AI SDK embeddings call
- `OpenAiExtractionAdapter` implements `ExtractionProvider` — wraps Laravel AI SDK text generation for entity extraction, importance scoring, summarization
- `AnthropicExtractionAdapter` implements `ExtractionProvider` — wraps Laravel AI SDK for Anthropic-only text extraction (no embeddings)
- Each adapter handles 429/Retry-After with jitter; logs to MemoryProviderUsage
- **Files**: `app/Support/Memory/Adapters/OpenAiEmbeddingAdapter.php`, `app/Support/Memory/Adapters/OpenAiExtractionAdapter.php`, `app/Support/Memory/Adapters/AnthropicExtractionAdapter.php` (3 new)

### 3.5 Redis Token-Bucket Rate Limiter
- Per provider+model key limiting using Redis MULTI/EXEC
- Configurable tokens/refill rate per provider via config/memory.php
- Pre-check before API call; blocks with calculated wait time if insufficient tokens
- **File**: `app/Support/Memory/RateLimiter/ProviderRateLimiter.php` (new)

### Phase 3 Tests
- MemoryAdapterFactory resolution: correct adapter for each provider setting
- Failover chain: primary fails → secondary resolved
- Rate limiter: token depletion → wait, refill → proceed
- Provider adapters: mock Laravel AI SDK responses, assert correct output parsing
- Provider usage logging: assert MemoryProviderUsage records created

---

## Phase 4: Working Memory (Layer 2) — Redis Buffer + Summarization
**Prerequisite: Phase 3 | Risk: Low**

### 4.1 WorkingMemoryBuffer
- Redis sorted set keyed by `memory:run:{run_id}:working`
- ZADD with microsecond timestamp score
- Retrieve last N messages via ZREVRANGEBYSCORE
- Count via ZCARD; eviction trigger when count > threshold (config `working_memory.summarization_threshold`)
- TTL via EXPIREAT on terminal status (config `working_memory.ttl_seconds`)
- **File**: `app/Support/Memory/WorkingMemoryBuffer.php` (new)

### 4.2 Eviction Strategies
- **No-API mode**: Oldest-first truncation via ZREMRANGEBYRANK (remove lowest-scored entries)
- **API mode**: Summarize oldest entries using ExtractionProvider::summarize() with separate summarization model; replace evicted entries with summary entry; fallback to truncation on provider failure
- Strategy selection based on MemoryCapabilityResolver mode
- **File**: Inline in WorkingMemoryBuffer or extracted to `app/Support/Memory/WorkingMemoryEvictionStrategy.php` (new)

### 4.3 MemoryWorkingBufferJob
- Fire-and-forget (0 retries, 0 backoff)
- Receives run_id + event_type + chunk content
- ZADD to Redis sorted set
- Must complete in <1ms typical (Redis only, no DB)
- Silent exception handling — never propagate errors
- Dispatched on `memory` queue
- **File**: `app/Jobs/Memory/MemoryWorkingBufferJob.php` (new)

### 4.4 Integration: RunEventWriter::appendOutput()
- After `persistRunStats()` (line 52 in RunEventWriter), add isolated dispatch of MemoryWorkingBufferJob
- Guard: `config('memory.enabled')`
- Wrap in try/catch with silent swallow — zero backpressure on 250ms poll loop
- **File**: `app/Support/Agent/RunEventWriter.php` (modify — after line 52)

### Phase 4 Tests
- WorkingMemoryBuffer: append, retrieve, count, TTL, eviction
- Summarization eviction: mock ExtractionProvider, verify summary stored
- Truncation eviction: verify oldest entries removed
- MemoryWorkingBufferJob: dispatched correctly, Redis state verified
- RunEventWriter integration: verify job dispatched without affecting existing behavior
- Zero-backpressure: verify exception in job dispatch doesn't propagate

---

## Phase 5: Long-term Memory (Layer 3) — Embeddings, Graph, Conversation Logs
**Prerequisite: Phase 3, Phase 4 | Risk: High (Neo4j + pgvector)**

### 5.1 Neo4jGraphStore
- Singleton wrapping `laudis/neo4j-php-client`
- Cypher query builder for entity CRUD with bi-temporal metadata (valid_from, valid_to, recorded_at, superseded_at)
- Application-level access control: all queries include classification constraint
- Entity extraction storage: nodes (Entity) + edges (RELATES_TO) with metadata
- Current-state query: WHERE valid_to IS NULL AND superseded_at IS NULL
- **File**: `app/Support/Memory/Neo4jGraphStore.php` (new)

### 5.2 LongTermMemoryWriter
- Orchestrates post-run memory formation:
  1. Always: persist conversation log entries to `memory_conversation_logs` (from Working Memory buffer or run events)
  2. API mode only: extract entities via ExtractionProvider::extractEntities()
  3. API mode only: generate embeddings via EmbeddingProvider::embed()
  4. API mode only: persist to pgvector (`memory_embeddings`) with HNSW indexing
  5. API mode only: store entities/relationships in Neo4j via Neo4jGraphStore
- Handles partial failures: if embeddings fail, still persist conv logs + graph; records MemoryFormationFailure for failed components
- Deduplication: content_hash check before embedding insert
- **File**: `app/Support/Memory/LongTermMemoryWriter.php` (new)

### 5.3 HybridRetriever
- Orchestrates parallel retrieval from available sources:
  - **Semantic**: pgvector cosine similarity query (skip if no pgvector or no embeddings)
  - **BM25**: PostgreSQL `ts_rank_cd` on `memory_embeddings` + `memory_conversation_logs` tsvector indexes
  - **Graph**: Neo4j Cypher traversal via Neo4jGraphStore (skip if Neo4j unavailable)
- RRF fusion with k=60, configurable per-source weights
- Classification filtering on all queries
- User-scoped
- Returns partial results when any source unavailable (never throws)
- **File**: `app/Support/Memory/HybridRetriever.php` (new)

### 5.4 MemoryFormationJob
- Dispatched from `ExecuteAgentRunJob::finalizeTerminal()` on terminal status
- Calls LongTermMemoryWriter with run context
- Retry: 5x with backoff [10, 30, 60, 120, 300]s
- On exhaustion: writes MemoryFormationFailure record with serialized payload
- Adapts behavior based on MemoryCapabilityResolver mode
- Queue: `memory`
- **File**: `app/Jobs/Memory/MemoryFormationJob.php` (new)

### 5.5 Integration: ExecuteAgentRunJob::finalizeTerminal()
- After `applyPathFailurePolicy()` (line 379), dispatch MemoryFormationJob
- Wrap in try/catch — memory must never block finalization
- Guard: `config('memory.enabled')`
- **File**: `app/Jobs/ExecuteAgentRunJob.php` (modify — after line 379)

### Phase 5 Tests
- Neo4jGraphStore: entity CRUD, bi-temporal metadata, classification filtering (requires Neo4j test instance or mock)
- LongTermMemoryWriter: full pipeline in API mode, conv-logs-only in no-API mode, partial failure handling
- HybridRetriever: RRF fusion math, graceful degradation (mock each source), classification filtering
- MemoryFormationJob: retry behavior, failure record creation, mode adaptation
- Integration: finalizeTerminal dispatches job without affecting existing behavior

---

## Phase 6: Consolidation, Forgetting, Artisan Commands
**Prerequisite: Phase 5 | Risk: Medium**

### 6.1 ConsolidationService
- Working→Core merging: summarize Working Memory insights into Core blocks (API mode)
- Vector deduplication: identify near-duplicate embeddings via cosine similarity, merge metadata
- MemoryFormationFailure backfill: retry failed formations with serialized payloads
- Checkpoint-resumable processing following `AgentMaintenanceCheckpoint` pattern
- **File**: `app/Support/Memory/ConsolidationService.php` (new)

### 6.2 ForgettingService
- Tiered pruning:
  - Working Memory: Redis TTL (handled by Redis EXPIREAT, verify cleanup)
  - Vectors: composite decay scoring `importance x recency_decay x access_frequency_bonus`, prune below 0.1 threshold
  - Graph entities: LLM-scored importance (API mode) or age-based (no-API), soft-delete below 0.2 after 90-day retention
  - Conversation logs: time-based retention (configurable days)
- Dry-run support: calculate what would be pruned without deleting
- Checkpoint-based processing for large datasets
- Follows `MaintenancePruneService` patterns (domains, chunked processing, audit logging)
- **File**: `app/Support/Memory/ForgettingService.php` (new)

### 6.3 MemoryConsolidationJob
- Scheduled + on-demand dispatch
- Calls ConsolidationService
- 3x retries, checkpoint-resumable
- Queue: `memory`
- **File**: `app/Jobs/Memory/MemoryConsolidationJob.php` (new)

### 6.4 MemoryForgettingJob
- Scheduled daily
- Calls ForgettingService
- 3x retries, dry-run support via constructor flag
- Queue: `memory`
- **File**: `app/Jobs/Memory/MemoryForgettingJob.php` (new)

### 6.5 Artisan Commands (4 commands)
- `memory:consolidate` — dispatches MemoryConsolidationJob; options: `--force` (run immediately vs dispatch), `--user=` filter
- `memory:prune` — dispatches MemoryForgettingJob; options: `--dry-run`, `--domain=` (vectors/graph/logs), `--user=` filter
- `memory:stats` — per-layer diagnostics (core block count, embedding count, graph entity count, working memory active keys, conv log count, failure backlog, provider usage summary); outputs JSON or table
- `memory:graph-snapshot` — point-in-time Neo4j graph inspection using bi-temporal metadata; options: `--at=` ISO datetime, `--user=`, `--format=` (json/table)
- **Files**: `app/Console/Commands/Memory/MemoryConsolidateCommand.php`, `MemoryPruneCommand.php`, `MemoryStatsCommand.php`, `MemoryGraphSnapshotCommand.php` (4 new)

### 6.6 Schedule Registration
- Add to `routes/console.php`:
  - `Schedule::command('memory:consolidate')->cron('0 */2 * * *')->withoutOverlapping(10);`
  - `Schedule::command('memory:prune')->dailyAt('03:30')->withoutOverlapping(10);`
- Guard: only schedule when `config('memory.enabled')`
- **File**: `routes/console.php` (modify)

### Phase 6 Tests
- ConsolidationService: Working→Core merge, dedup, backfill, checkpoint resume
- ForgettingService: decay scoring math, threshold pruning, dry-run mode, domain filtering
- Artisan commands: output format, option parsing, guard when disabled
- Schedule: commands registered only when memory enabled

---

## Phase 7: Context Injection + HTTP API
**Prerequisite: Phase 5 | Risk: Medium**

### 7.1 MemoryContextBuilder
- Generates wrapper markdown file:
  1. Core Memory preamble (agent_persona + user_profile blocks)
  2. Retrieved context from HybridRetriever (relevant memories for current task)
  3. Original task content (from job's task_markdown_path)
- Follows `TaskMarkdownStorage` pattern: date subdirs (`memory/context/YYYY/MM/DD/`), UUID filenames
- Adaptive token budget: 5% of runner context window (from runner config), clamped to [1000..8000]
- Summarize/truncate retrieved context if budget exceeded
- Returns absolute file path for use as effective `task_markdown_path`
- **File**: `app/Support/Memory/MemoryContextBuilder.php` (new)

### 7.2 Integration: ExecuteAgentRunJob::handle()
- Between run loading (line 43) and `renderTokens()` (line 91), insert MemoryContextBuilder call
- Override `$run->job->task_markdown_path` transiently (do not persist to DB)
- Guard: `config('memory.enabled')` + `config('memory.context_injection.enabled')`
- Must not modify CommandTemplateRenderer
- **File**: `app/Jobs/ExecuteAgentRunJob.php` (modify — between lines 89-91)

### 7.3 Integration: ExecuteAgentRunJob::mergedEnvironment()
- Add `MEMORY_API_BASE_URL` after line 298 (programmatic injection)
- Value: `config('app.url').'/agent/api/v1/memory'`
- Bypasses EnvPolicy forbidden key pattern (not in user env_json)
- Guard: `config('memory.enabled')`
- **File**: `app/Jobs/ExecuteAgentRunJob.php` (modify — after line 298)

### 7.4 Integration: config/agent.php
- Add memory context base to `allowed_task_markdown_bases`
- Path: `storage_path('app/memory/context')` or equivalent
- **File**: `config/agent.php` (modify — line 29 area)

### 7.5 HTTP API Controller + Routes (12 endpoints)
All memory endpoints are registered as a `memory/` sub-group inside the existing `agent/api/v1` route prefix in `routes/api.php`.

- `MemorySettingsController`:
  - `GET /agent/api/v1/memory/settings` — read all settings (keys masked)
  - `PUT /agent/api/v1/memory/settings` — update settings (batch)
  - `POST /agent/api/v1/memory/settings/test-connection` — test provider connectivity + report capabilities
  - `GET /agent/api/v1/memory/settings/capabilities` — operating mode + feature flags
- `MemoryCoreBlockController`:
  - `GET /agent/api/v1/memory/core-blocks` — list blocks for authenticated user
  - `GET /agent/api/v1/memory/core-blocks/{key}` — read single block
  - `PUT /agent/api/v1/memory/core-blocks/{key}` — create/update block (self-edit)
  - `DELETE /agent/api/v1/memory/core-blocks/{key}` — soft-delete block
- `MemoryRetrievalController`:
  - `POST /agent/api/v1/memory/retrieve` — hybrid retrieval query
- `MemoryWorkingController`:
  - `POST /agent/api/v1/memory/working/append` — append to working memory
  - `GET /agent/api/v1/memory/working/{runId}` — read working memory for run
- `MemoryStatsController`:
  - `GET /agent/api/v1/memory/stats` — diagnostics + provider usage
- **Files**: `app/Http/Controllers/Api/V1/Memory/MemorySettingsController.php`, `MemoryCoreBlockController.php`, `MemoryRetrievalController.php`, `MemoryWorkingController.php`, `MemoryStatsController.php` (5 new controllers)
- **Route registration**: Add `memory/` sub-group inside the existing `Route::prefix('agent/api/v1')` group in `routes/api.php` (line 16 area)

### 7.6 Request Validation + Rate Limiting
- Form Requests for each write endpoint (UpdateMemorySettingsRequest, UpdateCoreBlockRequest, RetrieveMemoryRequest)
- Dedicated throttle buckets: `memory-reads` (120/min), `memory-writes` (30/min)
- Register in AppServiceProvider or MemoryServiceProvider
- **Files**: `app/Http/Requests/Memory/*.php` (3+ new), rate limiter registration

### 7.7 API Middleware
- Sanctum auth, AgentApiVersionHeader (existing), user scoping
- Separate throttle groups to prevent memory traffic from starving job-control APIs
- **File**: Route middleware configuration

### Phase 7 Tests
- MemoryContextBuilder: file generation, token budget clamping, adaptive truncation
- Context injection integration: verify wrapper file used, original task content preserved
- MEMORY_API_BASE_URL injection: verify value is `config('app.url').'/agent/api/v1/memory'` in merged env
- HTTP API: full request/response cycle for all 12 endpoints under `/agent/api/v1/memory/` prefix
- Rate limiting: separate buckets enforced
- Auth: Sanctum guard, user scoping

---

## Phase 8: Integration Testing, Performance Validation
**Prerequisite: Phase 7 | Risk: Low**

### 8.1 End-to-End Integration Tests
- Full lifecycle: create job → dispatch run → working memory populated → run completes → formation job fires → long-term memory written → retrieval returns results → next run context includes memories
- No-API mode: full lifecycle with zero provider calls
- API mode: full lifecycle with mocked provider responses
- Degraded mode: Anthropic-only behavior verified

### 8.2 Performance Validation
- MemoryWorkingBufferJob latency: assert <1ms Redis ZADD
- RunEventWriter integration: verify zero measurable impact on 250ms poll loop
- MemoryFormationJob: verify async — no blocking of finalizeTerminal
- HybridRetriever: verify parallel source queries, reasonable timeout handling
- Context injection: verify file I/O latency acceptable

### 8.3 Feature Flag Verification
- `MEMORY_ENABLED=false`: entire system inert, zero side effects, no Redis/Neo4j connections
- `MEMORY_ENABLED=true, MEMORY_API_ENABLED=false`: no-API mode, BM25 only
- `MEMORY_ENABLED=true, MEMORY_API_ENABLED=true`: full pipeline
- Provider-specific degradation verified

### 8.4 Migration Safety
- Run full migration suite on clean PostgreSQL 18 database
- Run on existing database with production-like data
- Verify rollback for each migration
- Verify pgvector extension migration is truly idempotent and non-blocking when extension is unavailable (`vector_installed=no` is the current state)

---

## Dependency Graph

```
Phase 1 (Foundation)
  └── Phase 2 (Core Memory + Settings)
        └── Phase 3 (Provider Abstraction)
              ├── Phase 4 (Working Memory)
              │     └── Phase 5 (Long-term Memory)
              │           ├── Phase 6 (Consolidation + Forgetting)
              │           └── Phase 7 (Context Injection + API)
              └── Phase 5 (Long-term Memory) [parallel start possible]
  └── Phase 8 (Integration Testing — after all phases)
```

---

## File Change Summary

### New Files (~35)
| Category | Count | Location |
|----------|-------|----------|
| Config | 1 | `config/memory.php` |
| Migrations | 8 | `database/migrations/2026_02_17_0*.php` |
| Models | 7 | `app/Models/Memory*.php` |
| Provider | 1 | `app/Providers/MemoryServiceProvider.php` |
| Contracts | 2 | `app/Support/Memory/Contracts/` |
| Services | 11 | `app/Support/Memory/` |
| Adapters | 3 | `app/Support/Memory/Adapters/` |
| Rate Limiter | 1 | `app/Support/Memory/RateLimiter/` |
| Validator | 1 | `app/Support/Memory/Validation/` |
| Jobs | 4 | `app/Jobs/Memory/` |
| Commands | 4 | `app/Console/Commands/Memory/` |
| Controllers | 5 | `app/Http/Controllers/Api/V1/Memory/` |
| Form Requests | 3+ | `app/Http/Requests/Memory/` |

### Modified Files (~8)
| File | Change |
|------|--------|
| `bootstrap/providers.php` | Add MemoryServiceProvider |
| `config/database.php` | Add Redis `memory` connection (DB 2) |
| `config/horizon.php` | Add supervisor-memory queue + waits |
| `config/agent.php` | Add memory context base to allowed_task_markdown_bases |
| `composer.json` | Add laravel/ai + laudis/neo4j-php-client |
| `routes/console.php` | Add memory:consolidate + memory:prune schedules |
| `routes/api.php` | Add `memory/` sub-group inside existing `agent/api/v1` prefix |
| `app/Jobs/ExecuteAgentRunJob.php` | 3 insertions: context builder (handle), formation job (finalizeTerminal), env injection (mergedEnvironment) |
| `app/Support/Agent/RunEventWriter.php` | 1 insertion: working buffer job dispatch (appendOutput) |
| `app/Providers/AppServiceProvider.php` | Add memory rate limiter definitions |

## Sections

- Phase 1: Foundation — Config, Migrations, Models, Provider Registration
- Phase 2: Core Memory (Layer 1) — CoreMemoryManager + Settings
- Phase 3: Provider Abstraction — Contracts, Adapters, Rate Limiting
- Phase 4: Working Memory (Layer 2) — Redis Buffer + Summarization
- Phase 5: Long-term Memory (Layer 3) — Embeddings, Graph, Conversation Logs
- Phase 6: Consolidation, Forgetting, Artisan Commands
- Phase 7: Context Injection + HTTP API
- Phase 8: Integration Testing, Performance Validation


## Risks

- R1: laravel/ai SDK maturity — Package is relatively new; API surface may change. Mitigation: Wrap all SDK calls behind our own EmbeddingProvider/ExtractionProvider contracts so the adapter layer absorbs breaking changes. Pin to specific version in composer.json.
- R2: pgvector extension availability — pgvector is currently NOT installed on the target database (vector_installed=no). System designed for graceful degradation to BM25+graph mode. Migration is idempotent and non-fatal. MemoryCapabilityResolver auto-detects availability at runtime.
- R3: Neo4j Community Edition limitations — No built-in RBAC, no HA clustering, no online backups. Mitigation: Application-level access control in Neo4jGraphStore. Single-instance acceptable for current <20 runs/day volume. Document upgrade path to Enterprise for future scaling.
- R4: Neo4j operational complexity — Adds a new external service dependency (bolt://localhost:7687) to the deployment topology. Mitigation: Feature-flag gated; system runs in BM25-only mode if Neo4j unavailable. Document Docker Compose setup for local development.
- R5: RunEventWriter integration sensitivity — Modifying the hot path (250ms poll loop) with fire-and-forget job dispatch. Mitigation: Dispatch wrapped in isolated try/catch with zero propagation. MemoryWorkingBufferJob is Redis-only (<1ms). Load test to verify zero measurable impact.
- R6: ExecuteAgentRunJob modification risk — Three insertion points in a critical job. Mitigation: All insertions guarded by config('memory.enabled') + try/catch. Memory failures never block run execution. Feature flag allows instant rollback without code deployment.
- R7: Context injection token budget accuracy — Token counting in PHP is approximate without a proper tokenizer. Mitigation: Use conservative character-to-token ratio (4 chars/token) with 10% safety margin. Hard ceiling at 8K tokens prevents over-injection. Adaptive budget self-corrects over time.
- R8: Provider cost control — LLM extraction and embedding calls have per-token costs that could accumulate. Mitigation: Medium-tier models (GPT-4o-mini for extraction, text-embedding-3-small for embeddings). Separate summarization model config for cheapest option. MemoryProviderUsage table tracks all costs. Proactive rate limiting prevents runaway spend.
- R9: Data consistency during async formation — Memory formation is async; a crash between run completion and formation job execution could lose memory data. Mitigation: MemoryFormationFailure table captures exhausted retries with full serialized payload for backfill. ConsolidationService retries failures every 2 hours.
- R10: Encrypted key rotation and APP_KEY dependency — Memory Settings API keys encrypted via Laravel encrypted cast depend on APP_KEY. APP_KEY rotation would invalidate all stored keys. Mitigation: Document key rotation procedure. Consider future support for re-encryption command.


## Assumptions

- A1: PostgreSQL 18 is the active database (DB_CONNECTION=pgsql confirmed) — no SQLite-to-PostgreSQL migration needed
- A2: Redis is available and operational (already used by Horizon for queue processing) — adding DB 2 for memory is a configuration-only change
- A3: pgvector 0.8.1 extension is a deployment target but is currently NOT installed (vector_installed=no) — system degrades gracefully to BM25+graph mode without blocking deployment
- A4: Neo4j 5.x LTS Community Edition will be deployed as a new service alongside the existing stack — Docker or native install, single instance, bolt protocol on default port 7687
- A5: laravel/ai package is stable enough for production use with OpenAI and Anthropic providers — adapter layer insulates us from SDK instability
- A6: Current run volume remains low (<20/day) for initial deployment — Horizon supervisor-memory with 3 workers is sufficient; scaling via HORIZON_MEMORY_MAX_PROCESSES env var when needed
- A7: APP_KEY is stable and will not be rotated during initial deployment — encrypted Memory Settings depend on it
- A8: The existing InterrogationSetting/AdapterFactory/MaintenancePruneService patterns are considered canonical and should be followed for consistency
- A9: No team/org-level memory scoping is needed in v1 — all memory is per-user only, with schema forward-compatible for future scope_type/scope_id
- A10: Layer 4 (Delegation Memory) will be implemented in a separate future effort — this plan covers Layers 1-3 only
- A11: Token counting approximation (4 chars/token) is acceptable for context budget calculation in v1 — a proper tokenizer integration can be added later if needed
- A12: The Horizon queue infrastructure has sufficient capacity to handle an additional supervisor-memory queue alongside existing agent and interrogation supervisors
- A13: All memory API endpoints follow the project convention of /agent/api/v1/ prefix (per AGENTS.md line 71) — memory routes are a sub-group at /agent/api/v1/memory/

