# Requirements Discovery Summary

Session: 3

# Four-Layer Memory Architecture — Agent Ops

## Overview
A modular, four-layer memory system integrated directly into the existing PHP 8.3 / Laravel 12 Agent Ops codebase at `/Users/garethdaine/Code/agent`. Layers 1–3 ship in v1; Layer 4 (Delegation Memory) defers to v2. Memory formation is fully async via Laravel Horizon queues, decoupled from the agent response path. Provider abstraction uses the **Laravel AI SDK** (`laravel/ai`) for multi-provider support. Memory provider/model/key selection is managed by Memory Settings + Laravel AI SDK adapter resolution; avoid duplicating provider credentials in legacy service config unless explicitly required by package internals. Informed by Letta/Mem0/Graphiti production patterns and Tomasev et al. (arXiv:2602.11865).

---

## Operating Modes

### No-API Mode (Base Memory — no provider keys required)
- Layer 1: Core Memory (full CRUD, version tracking, self-edit via tool calls)
- Layer 2: Working Memory (Redis sorted sets, TTL eviction — oldest-first truncation, no LLM summarization)
- Retrieval: **BM25-only** via PostgreSQL tsvector (no semantic embeddings, no graph extraction)
- Conversation logs always persisted to `memory_conversation_logs`
- Zero LLM calls, zero embedding calls, zero provider keys needed

### API Mode (Advanced Memory — provider keys configured + `api_features_enabled`)
- All Base Memory features PLUS:
- LLM extraction/summarization (entity extraction, importance scoring, recursive summarization)
- Semantic embeddings (requires **embeddings-capable provider** — e.g., OpenAI)
- Full hybrid retrieval: semantic + BM25 + graph traversal via RRF
- Neo4j knowledge graph population via entity extraction

### Anthropic-Only Constraint
When only Anthropic API key configured (no embeddings provider):
- Text extraction enabled; semantic embeddings disabled (Anthropic has no embeddings API)
- Retrieval degrades to BM25 + graph traversal only
- `/memory/v1/settings/capabilities` reports degraded mode

---

## Resolved Architectural Decisions (22 total)

### D1: Runtime & Integration
- **PHP 8.3 + Laravel 12** — integrated into existing Agent Ops, not a separate service
- **Laravel AI SDK** (`laravel/ai`) for provider abstraction — replaces custom HTTP clients
- Laravel Horizon with dedicated `supervisor-memory` queue
- Already running on **PostgreSQL** (`DB_CONNECTION=pgsql` confirmed)

### D2: Memory Identity & Ownership
- **Hybrid**: Core/Long-term scoped per `user_id` (from `AgentJob.user_id`); Working Memory per `AgentJobRun.id`; operational blocks per `AgentJob.id`
- Per-user only in v1 — no team/org scoping; schema designed forward-compatible for future `scope_type`/`scope_id`

### D3: Memory Formation Tap Points
- **Dual-path**: Streaming intercept in `RunEventWriter::appendOutput()` (line 40) for Working Memory; post-run batch `MemoryFormationJob` dispatched from `ExecuteAgentRunJob::finalizeTerminal()` (line 306) for Long-term Memory

### D4: LLM / Embedding Providers
- **Adapter pattern**: `EmbeddingProvider` and `ExtractionProvider` interfaces following `InterrogationRunnerAdapter` contract pattern
- **Factory**: `MemoryAdapterFactory` with `match()` resolution following `AdapterFactory` pattern
- **Three model tiers**: Extraction (GPT-4o-mini default), Summarization (cheaper/faster model, separate config), Embeddings (text-embedding-3-small)
- **Rate limiting**: Proactive Redis token-bucket per provider+model in `MemoryAdapterFactory` + reactive 429/Retry-After handling with jitter
- Memory provider/model/key selection is managed by Memory Settings + Laravel AI SDK adapter resolution; avoid duplicating provider credentials in legacy service config unless explicitly required by package internals

### D5: Storage Topology
- **PostgreSQL 18 is active; pgvector 0.8.1 is a deployment prerequisite/target for semantic vector features. Until extension install, run in BM25 + graph mode.**
- **Neo4j 5.x LTS Community** for bi-temporal knowledge graph (new external dependency)
- **Redis DB 2** for Working Memory sorted sets

### D6: Core Memory Block Structure (Layer 1)
- **Hybrid blocks**: Freeform text for identity (`agent_persona`, `user_profile`); schema-enforced JSON for operational (`task_state`, `tool_results_cache`, `active_goals`)
- Letta-style self-editing via HTTP API tool calls; version tracking on every update
- Sleep-time consolidation merges Working Memory insights into Core blocks

### D7: Working Memory (Layer 2)
- Redis sorted sets keyed by `memory:run:{run_id}:working`, scored by microsecond timestamp
- Last 15 messages; eviction via LLM summarization (API mode with separate summarization model) or oldest-first truncation (no-API mode)
- TTL: 2 hours post terminal status via Redis EXPIREAT

### D8: Long-term Memory (Layer 3)
- **Vector store**: pgvector `memory_embeddings`, 1536d HNSW index (populated only with embeddings provider; requires pgvector extension)
- **Knowledge graph**: Neo4j Community with bi-temporal metadata stored from day one; current-state queries only in v1
- **Conversation logs**: `memory_conversation_logs` always populated in both modes
- **Retrieval**: Full RRF when all sources available; graceful degradation based on provider/extension availability

### D9: Forgetting Policy
- **Tiered**: Working Memory = Redis TTL (2h); Vectors = composite decay (`importance × recency_decay × access_frequency_bonus`), prune below 0.1; Graph entities = LLM-scored importance, soft-delete below 0.2 after 90-day retention; Conv logs = time-based retention

### D10: Retrieval Fusion
- **Reciprocal Rank Fusion (RRF)** with k=60; per-source weights configurable (semantic/keyword/graph, default 1.0 each)

### D11: Consolidation Scheduler
- **Event-driven + periodic**: `MemoryFormationJob` on terminal status + `memory:consolidate` every 2 hours
- Resumable checkpoints following `AgentMaintenanceCheckpoint` pattern

### D12: Access Control
- **Classification levels**: `public`, `internal` (default), `confidential`
- Enforced at SQL WHERE / Cypher MATCH query level
- Application-level enforcement (Neo4j Community has no built-in RBAC)

### D13: V1 Scope
- Full Layers 1–3 with consolidation, forgetting, hybrid RRF retrieval, memory classification
- Deferred: Layer 4 (Delegation Memory), FastAPI endpoints, framework integrations (LangGraph/CrewAI)

### D14: Context Injection
- **Wrapper file approach**: `MemoryContextBuilder` generates temp markdown with Core preamble + retrieved context + task content
- Follows `TaskMarkdownStorage` pattern: date subdirs, UUID filenames, under `memory/context/` prefix
- Overrides effective `task_markdown_path` in `ExecuteAgentRunJob::handle()` before `renderTokens()`
- **Adaptive budget**: Scales as percentage of runner's context window (5% default); hard ceiling 8K tokens; summarize/truncate if exceeded

### D15: Error Handling
- **Retry with eventual consistency**: Formation jobs retry 5× with exponential backoff [10s, 30s, 60s, 120s, 300s]
- Exhausted retries → `MemoryFormationFailure` record for backfill via `memory:consolidate`
- Retrieval returns partial results; agent runs **never blocked** by memory failures

### D16: Infrastructure Versions
- PostgreSQL 18 is active; pgvector 0.8.1 is a deployment prerequisite/target for semantic vector features. Until extension install, run in BM25 + graph mode.
- Neo4j 5.x LTS Community Edition (single-instance, app-level access control)
- When pgvector is installed: HNSW indexes with parallel builds, m=16, ef_construction=128

### D17: Provider Capability Model
- Providers classified: `text_generation` and/or `embeddings`
- `MemoryCapabilityResolver` auto-detects and adjusts layer behavior

### D18: API Mode Gating
- `MEMORY_ENABLED` (master) + `MEMORY_API_ENABLED` (LLM/embedding toggle)
- No-API = L1 + L2 + BM25 + conv logs; API = full pipeline

### D19: Provider Failover
- Text extraction: fails over across text-capable providers (OpenAI ↔ Anthropic)
- Embeddings: fails over only across embeddings-capable providers; never to text-only
- Managed by Laravel AI SDK failover config

### D20: Bi-Temporal Graph Queries
- v1: store-only bi-temporality; current-state retrieval for all production paths
- Admin/debug: `memory:graph-snapshot` Artisan command for point-in-time inspection
- Full temporal query support planned for v1.1/v2

### D21: Run Volume & Sizing
- Current expected volume is low (<20/day); architecture is configured to scale to medium usage via env-configurable worker counts and rate-limit settings
- `HORIZON_MEMORY_MAX_PROCESSES` default 3; all worker counts, rate limits, TTLs configurable via env vars without code changes

### D22: Env Injection Safety
- `MEMORY_API_BASE_URL` injected programmatically in `mergedEnvironment()` (not through user `env_json`), bypassing `EnvPolicy.forbidden_env_key_pattern`

---

## Memory Settings (User/Workspace-level, `memory_settings` table)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint nullable FK | NULL = workspace default; set = per-user override |
| key | varchar(100) | unique per user_id |
| value | text | Laravel `encrypted` cast for secrets |
| created_at / updated_at | timestamptz | |

### Setting Keys
| Key | Default | Notes |
|-----|---------|-------|
| `memory_enabled` | false | Master toggle |
| `api_features_enabled` | false | LLM + embeddings toggle |
| `extraction_provider` | null | 'openai', 'anthropic', etc. |
| `extraction_model` | null | e.g. 'gpt-4o-mini' |
| `summarization_provider` | null | Separate cheaper model for Working Memory |
| `summarization_model` | null | e.g. 'gpt-4.1-nano' |
| `embeddings_provider` | null | Must be embeddings-capable |
| `embeddings_model` | null | e.g. 'text-embedding-3-small' |
| `provider_key_openai` | null | Encrypted at rest (AES-256-CBC via APP_KEY) |
| `provider_key_anthropic` | null | Encrypted at rest |
| `embedding_dimensions` | 1536 | Per provider/model |

### Secure Key Handling
- All API keys encrypted via Laravel `encrypted` cast; masked in API/UI (last 4 chars: `sk-...a1b2`)
- Rotation via PUT; immediate replacement; zero downtime
- Audit: key set/changed/removed events logged; key values NEVER logged
- Provider usage (provider, model, tokens, cost) logged in `memory_provider_usage`

---

## New Database Tables (PostgreSQL 18; pgvector 0.8.1 when installed)

### memory_core_blocks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK NOT NULL | users.id |
| job_id | bigint nullable FK | agent_jobs.id (operational blocks) |
| block_type | varchar(20) | 'identity' or 'operational' |
| block_key | varchar(100) | 'agent_persona', 'user_profile', 'task_state', 'tool_results_cache', 'active_goals' |
| content_text | text nullable | freeform identity blocks |
| content_json | jsonb nullable | schema-enforced operational blocks |
| classification | varchar(20) default 'internal' | 'public', 'internal', 'confidential' |
| version | integer default 1 | incremented on every update |
| created_at / updated_at | timestamptz | |
| **Indexes** | | unique(user_id, block_key, job_id), idx(user_id, classification) |

### memory_embeddings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK NOT NULL | users.id |
| source_type | varchar(30) | 'conversation', 'extraction', 'summary' |
| source_id | varchar(100) | polymorphic reference |
| content | text | original text embedded |
| content_hash | varchar(64) | SHA-256 dedup |
| embedding | vector(1536) | pgvector; nullable when extension not installed or no embeddings provider |
| metadata_json | jsonb nullable | flexible metadata |
| classification | varchar(20) default 'internal' | access control |
| importance_score | double precision default 0.5 | 0.0–1.0 |
| access_count | integer default 0 | frequency bonus |
| last_accessed_at | timestamptz nullable | recency decay |
| created_at | timestamptz | immutable |
| **Indexes** | | HNSW(embedding vector_cosine_ops, m=16, ef_construction=128) — requires pgvector, GIN(to_tsvector('english', content)), idx(user_id, classification), idx(content_hash) |

### memory_conversation_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK | users.id |
| run_id | bigint FK | agent_job_runs.id |
| job_id | bigint FK | agent_jobs.id |
| role | varchar(20) | 'system', 'user', 'assistant', 'tool' |
| content | text | message content |
| sequence | integer | order within run |
| event_type | varchar(20) | 'stdout', 'stderr', 'lifecycle' |
| classification | varchar(20) default 'internal' | access control |
| created_at | timestamptz | immutable |
| **Indexes** | | idx(user_id, run_id, sequence), idx(user_id, classification), GIN(to_tsvector('english', content)) |

### memory_consolidation_log
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint nullable FK | users.id |
| consolidation_type | varchar(30) | 'working_to_core', 'dedup_vectors', 'graph_prune', 'backfill' |
| source_count | integer | items processed |
| result_summary | text nullable | outcome |
| checkpoint_json | jsonb nullable | resumable state |
| created_at | timestamptz | immutable |

### memory_formation_failures
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| run_id | bigint FK | agent_job_runs.id |
| user_id | bigint FK | users.id |
| failure_type | varchar(30) | 'embedding', 'graph_extraction', 'conversation_log', 'full' |
| error_message | text | last error |
| attempts | integer | max 5 |
| backfilled_at | timestamptz nullable | set on recovery |
| payload_json | jsonb | serialized context for retry |
| created_at | timestamptz | immutable |
| **Indexes** | | partial idx WHERE backfilled_at IS NULL |

### memory_settings
(See Memory Settings section above)

### memory_provider_usage
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK | users.id |
| run_id | bigint nullable FK | agent_job_runs.id |
| provider | varchar(30) | 'openai', 'anthropic' |
| model | varchar(100) | model identifier |
| operation | varchar(30) | 'embedding', 'extraction', 'summarization', 'importance_scoring' |
| input_tokens | integer | tokens consumed |
| output_tokens | integer nullable | null for embeddings |
| cost_estimate_usd | double precision nullable | estimated cost |
| created_at | timestamptz | immutable |

### pgvector extension migration
```sql
CREATE EXTENSION IF NOT EXISTS vector;
```
Note: This is a deployment prerequisite for semantic vector features. If the extension cannot be installed (permissions, hosting constraints), the system runs in BM25 + graph mode until it is available.

Total: 8 new migrations (pgvector extension + memory_core_blocks + memory_embeddings + memory_conversation_logs + memory_consolidation_log + memory_formation_failures + memory_settings + memory_provider_usage)

---

## New Services (app/Support/Memory/)

| Service | Responsibility |
|---------|---------------|
| `MemoryContextBuilder` | Assembles wrapper markdown: Core preamble + retrieved context (adaptive budget) + task content |
| `CoreMemoryManager` | CRUD with versioning, JSON schema validation for operational blocks, AuditLogger integration |
| `WorkingMemoryBuffer` | Redis sorted set ops (ZADD/ZRANGEBYSCORE/ZREMRANGEBYRANK), summarization trigger, TTL |
| `LongTermMemoryWriter` | Embeddings + graph extraction + conv logs; skips LLM steps in no-API mode |
| `HybridRetriever` | Orchestrates available sources (pgvector/BM25/Neo4j) based on mode; RRF fusion; partial results |
| `ConsolidationService` | Periodic + event-driven; Working→Core merge; vector dedup; failure backfill; resumable checkpoints |
| `ForgettingService` | Tiered pruning: TTL/decay/importance; dry-run; follows MaintenancePruneService pattern |
| `MemoryAdapterFactory` | Resolves EmbeddingProvider/ExtractionProvider via Laravel AI SDK + Memory Settings; includes Redis token-bucket rate limiter |
| `Neo4jGraphStore` | Cypher wrapper isolating `laudis/neo4j-php-client`; bi-temporal CRUD; app-level access control |
| `MemoryCapabilityResolver` | Inspects configured providers + pgvector availability; determines mode (no-api/api/degraded) |
| `MemorySettingsService` | Settings read/write with encryption, masking, validation, capability detection |

---

## New Contracts (app/Support/Memory/Contracts/)

### EmbeddingProvider
```php
interface EmbeddingProvider {
    /** @return array<int, float> */
    public function embed(string $text): array;
    /** @return array<int, array<int, float>> */
    public function embedBatch(array $texts): array;
    public function dimensions(): int;
}
```

### ExtractionProvider
```php
interface ExtractionProvider {
    /** @return array<int, array{entity: string, type: string, relationships: array}> */
    public function extractEntities(string $text): array;
    /** @return float 0.0-1.0 */
    public function scoreImportance(string $text): float;
    public function summarize(string $text, int $maxTokens): string;
}
```

---

## New Jobs (Horizon `supervisor-memory` queue)

| Job | Trigger | Retry | Notes |
|-----|---------|-------|-------|
| `MemoryWorkingBufferJob` | `RunEventWriter::appendOutput()` per chunk | 0 (fire-and-forget) | Redis ZADD only, < 1ms |
| `MemoryFormationJob` | `finalizeTerminal()` terminal status | 5× [10,30,60,120,300]s; writes MemoryFormationFailure on exhaustion | Adapts to mode |
| `MemoryConsolidationJob` | Scheduled every 2h + failure backfill | 3× | Checkpoint-resumable |
| `MemoryForgettingJob` | Scheduled daily | 3× | Dry-run support |

---

## New Artisan Commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `memory:consolidate` | `0 */2 * * *` | Consolidation + failure backfill |
| `memory:prune` | `0 3 * * *` | Scheduled forgetting |
| `memory:stats` | Manual | Per-layer diagnostics + provider usage |
| `memory:graph-snapshot` | Manual | Point-in-time graph state inspection (admin/debug) |

---

## Config: config/memory.php

```php
return [
    'enabled' => env('MEMORY_ENABLED', false),
    'api_features_enabled' => env('MEMORY_API_ENABLED', false),

    'providers' => [
        'extraction' => [
            'default' => env('MEMORY_EXTRACTION_PROVIDER', 'openai'),
            'model' => env('MEMORY_EXTRACTION_MODEL', 'gpt-4o-mini'),
            'failover' => ['openai', 'anthropic'],
        ],
        'summarization' => [
            'default' => env('MEMORY_SUMMARIZATION_PROVIDER', 'openai'),
            'model' => env('MEMORY_SUMMARIZATION_MODEL', 'gpt-4.1-nano'),
            'failover_to_extraction' => true,
        ],
        'embeddings' => [
            'default' => env('MEMORY_EMBEDDINGS_PROVIDER', 'openai'),
            'model' => env('MEMORY_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('MEMORY_EMBEDDING_DIMENSIONS', 1536),
            'failover' => ['openai'],
        ],
    ],

    'working_memory' => [
        'redis_connection' => 'memory',
        'max_messages' => (int) env('MEMORY_WORKING_MAX_MESSAGES', 15),
        'ttl_seconds' => (int) env('MEMORY_WORKING_TTL', 7200),
        'summarization_threshold' => (int) env('MEMORY_WORKING_SUMMARIZE_AT', 10),
    ],

    'retrieval' => [
        'rrf_k' => (int) env('MEMORY_RRF_K', 60),
        'max_results' => (int) env('MEMORY_RETRIEVAL_MAX', 20),
        'semantic_weight' => (float) env('MEMORY_WEIGHT_SEMANTIC', 1.0),
        'keyword_weight' => (float) env('MEMORY_WEIGHT_KEYWORD', 1.0),
        'graph_weight' => (float) env('MEMORY_WEIGHT_GRAPH', 1.0),
    ],

    'forgetting' => [
        'vector_decay_half_life_days' => (int) env('MEMORY_VECTOR_HALF_LIFE', 30),
        'vector_prune_threshold' => (float) env('MEMORY_VECTOR_PRUNE_THRESHOLD', 0.1),
        'graph_importance_threshold' => (float) env('MEMORY_GRAPH_IMPORTANCE_THRESHOLD', 0.2),
        'graph_retention_days' => (int) env('MEMORY_GRAPH_RETENTION_DAYS', 90),
    ],

    'consolidation' => [
        'schedule_cron' => '0 */2 * * *',
        'chunk_size' => (int) env('MEMORY_CONSOLIDATION_CHUNK', 100),
    ],

    'formation' => [
        'max_retries' => 5,
        'backoff_seconds' => [10, 30, 60, 120, 300],
    ],

    'neo4j' => [
        'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),
        'username' => env('NEO4J_USERNAME', 'neo4j'),
        'password' => env('NEO4J_PASSWORD', ''),
        'database' => env('NEO4J_DATABASE', 'neo4j'),
    ],

    'classification_default' => 'internal',

    'context_injection' => [
        'enabled' => true,
        'base_subdir' => 'memory/context',
        'max_context_tokens' => (int) env('MEMORY_MAX_CONTEXT_TOKENS', 4000),
        'hard_ceiling_tokens' => 8000,
        'context_budget_pct' => (float) env('MEMORY_CONTEXT_BUDGET_PCT', 0.05),
    ],

    'rate_limiting' => [
        'enabled' => true,
        'redis_connection' => 'memory',
    ],

    'security' => [
        'key_mask_visible_chars' => 4,
        'audit_provider_usage' => true,
        'never_log_secrets' => true,
    ],
];
```

Note: Provider credentials are NOT duplicated in `config/services.php`. Memory provider/model/key selection is managed entirely by Memory Settings + Laravel AI SDK adapter resolution. Only add provider config to `config/services.php` if explicitly required by Laravel AI SDK package internals for service discovery.

---

## Integration Points (Existing Code Modifications)

### 1. ExecuteAgentRunJob::handle() (line 35)
Between run loading (line 43) and `renderTokens()` (line 91), insert `MemoryContextBuilder::build()`. Override effective `task_markdown_path` transiently. Guard: `config('memory.enabled')`. Works in both modes.

### 2. ExecuteAgentRunJob::finalizeTerminal() (line 306)
After `applyPathFailurePolicy()` (line 379), dispatch `MemoryFormationJob` (try/catch — memory must never block finalization).

### 3. ExecuteAgentRunJob::mergedEnvironment() (line 284)
Add `MEMORY_API_BASE_URL` after line 298 (programmatic injection, bypasses EnvPolicy).

### 4. RunEventWriter::appendOutput() (line 40)
After `persistRunStats()` (line 52), isolated fire-and-forget `MemoryWorkingBufferJob` (silent catch).

### 5. config/horizon.php — Add `supervisor-memory` (after line 228); add `'redis:memory' => 30` to waits
### 6. config/database.php — Add Redis `memory` connection with DB 2 (after line 179)
### 7. config/agent.php — Add memory context base to `allowed_task_markdown_bases` (line 29)
### 8. composer.json — Add `laravel/ai` and `laudis/neo4j-php-client`

---

## HTTP API Endpoints (Sanctum-guarded, dedicated throttle buckets)

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/memory/v1/settings` | Read settings (keys masked) |
| PUT | `/memory/v1/settings` | Update settings |
| POST | `/memory/v1/settings/test-connection` | Test provider, report capabilities |
| GET | `/memory/v1/settings/capabilities` | Operating mode + features |
| GET | `/memory/v1/core-blocks` | List core blocks |
| GET | `/memory/v1/core-blocks/{key}` | Read single block |
| PUT | `/memory/v1/core-blocks/{key}` | Self-edit block |
| DELETE | `/memory/v1/core-blocks/{key}` | Soft-delete block |
| POST | `/memory/v1/retrieve` | Hybrid retrieval (adapts to mode) |
| POST | `/memory/v1/working/append` | Append to Working Memory |
| GET | `/memory/v1/working/{runId}` | Read Working Memory |
| GET | `/memory/v1/stats` | Diagnostics + provider usage |

Middleware: `auth:sanctum`, version header, tenant/user scoping. Separate throttle buckets for read vs write.

---

## MemoryServiceProvider (bootstrap/providers.php)
- Binds `EmbeddingProvider` + `ExtractionProvider` singletons via `MemoryAdapterFactory`
- Registers Neo4j client singleton (`laudis/neo4j-php-client`)
- Conditionally registers memory API routes when `config('memory.enabled')`
- Registers scheduled commands (`memory:consolidate`, `memory:prune`)
- All bindings feature-flag gated on `config('memory.enabled')`

---

## Audit Trail
All memory mutations logged via existing `AuditLogger::recordSystemAction()`.
New target_types: `memory_core_block`, `memory_embedding`, `memory_graph_entity`, `memory_consolidation`, `memory_prune`, `memory_settings`.
Immutable append-only, consistent with `AgentAuditLog` model.

## Goals

- G1: Build a four-layer memory architecture (Layers 1–3 in v1, Layer 4 deferred) integrated into the existing PHP 8.3 / Laravel 12 Agent Ops codebase at /Users/garethdaine/Code/agent
- G2: Layer 1 (Core Memory) — Letta-style editable structured blocks (hybrid: freeform text for identity blocks agent_persona/user_profile, schema-enforced JSON for operational blocks task_state/tool_results_cache/active_goals) with self-edit via HTTP API tool calls, version tracking, and sleep-time async consolidation via MemoryConsolidationJob
- G3: Layer 2 (Working Memory) — Redis sorted sets (DB 2) keyed by memory:run:{run_id}:working, last 15 messages verbatim, eviction via LLM summarization (API mode with separate summarization model) or oldest-first truncation (no-API mode), TTL 2 hours post terminal status
- G4: Layer 3 (Long-term Memory) — Hybrid persistent store: pgvector embeddings (1536d HNSW, requires pgvector extension), Neo4j 5.x LTS Community bi-temporal knowledge graph (current-state queries in v1, temporal metadata stored from day one), PostgreSQL conversation logs (always populated)
- G5: Implement hybrid retrieval pipeline using Reciprocal Rank Fusion (RRF, k=60) combining pgvector cosine similarity + PostgreSQL tsvector BM25 + Neo4j Cypher traversal, with configurable per-source weights and graceful degradation when sources unavailable
- G6: Decouple memory formation from response path — dual-tap: streaming intercept in RunEventWriter::appendOutput() for Working Memory (fire-and-forget MemoryWorkingBufferJob), post-run batch MemoryFormationJob from ExecuteAgentRunJob::finalizeTerminal() for Long-term Memory
- G7: Implement tiered controlled forgetting — Working Memory: Redis TTL; Vectors: composite decay (importance × recency_decay × access_frequency_bonus) prune below 0.1; Graph: LLM-scored importance prune below 0.2 after 90-day retention; Conv logs: time-based retention
- G8: Build adapter-based provider abstraction using Laravel AI SDK (laravel/ai) with EmbeddingProvider and ExtractionProvider contracts, MemoryAdapterFactory with match() resolution, three model tiers (extraction/summarization/embeddings), proactive Redis token-bucket rate limiting + reactive 429 handling
- G9: Support two operating modes — No-API Mode (L1 + L2 + BM25 + conv logs, zero provider keys) and API Mode (full pipeline with LLM extraction, embeddings, hybrid RRF, graph population), with automatic capability detection via MemoryCapabilityResolver
- G10: Implement Memory Settings (memory_settings table) for user/workspace-level configuration of providers, models, API keys (encrypted at rest via Laravel encrypted cast), with secure key handling, masking, rotation, and audit logging
- G11: Context injection via wrapper file approach — MemoryContextBuilder generates temp markdown (Core preamble + retrieved context + task content) following TaskMarkdownStorage pattern, with adaptive budget (5% of runner context window, hard ceiling 8K tokens, clamp [1000..8000])
- G12: Implement access control with memory classification levels (public/internal/confidential) enforced at SQL WHERE and Cypher MATCH query level on all memory tables and Neo4j nodes
- G13: Error handling with retry and eventual consistency — formation jobs retry 5× with exponential backoff [10s,30s,60s,120s,300s], exhausted retries write MemoryFormationFailure for backfill, retrieval returns partial results, agent runs never blocked
- G14: Provider usage tracking via memory_provider_usage table (provider, model, operation, token counts, cost estimates) for cost management
- G15: Expose HTTP API endpoints under /memory/v1/ (Sanctum-guarded, dedicated throttle buckets) for settings, core blocks CRUD, hybrid retrieval, working memory operations, and diagnostics


## Constraints

- C1: Runtime is PHP 8.3 + Laravel 12 integrated into existing Agent Ops — no separate Python/Node services for v1
- C2: PostgreSQL 18 is active; pgvector 0.8.1 is a deployment prerequisite/target for semantic vector features. Until extension install, run in BM25 + graph mode
- C3: Neo4j 5.x LTS Community Edition — application-level access control enforced in Neo4jGraphStore (no built-in RBAC); upgrade to Enterprise only if DB-native RBAC, tenant isolation, or HA clustering needed
- C4: Memory formation is fully async via Horizon queues, decoupled from agent response path — memory failures must NEVER block or fail agent runs
- C5: Current expected volume is low (<20/day); architecture is configured to scale to medium usage via env-configurable worker counts and rate-limit settings (HORIZON_MEMORY_MAX_PROCESSES default 3)
- C6: Memory provider/model/key selection is managed by Memory Settings + Laravel AI SDK adapter resolution; avoid duplicating provider credentials in legacy service config (config/services.php) unless explicitly required by package internals
- C7: All provider API keys encrypted at rest via Laravel encrypted cast (AES-256-CBC via APP_KEY); keys masked in all API/UI output; key values NEVER logged in audit trail
- C8: Per-user memory scoping only in v1 — no team/org scoping; schema forward-compatible for future scope_type/scope_id
- C9: No SQLite→PostgreSQL migration needed (already on PostgreSQL); only additive migrations for new memory tables/indexes/extensions
- C10: MemoryWorkingBufferJob dispatched from RunEventWriter::appendOutput() must be fire-and-forget with zero backpressure on the 250ms poll loop
- C11: Context injection wrapper file must not modify CommandTemplateRenderer — override effective task_markdown_path transiently on $run->job before renderTokens()
- C12: MEMORY_API_BASE_URL injected programmatically in mergedEnvironment() (not via user env_json), bypassing EnvPolicy.forbidden_env_key_pattern
- C13: Use medium-tier models for extraction (GPT-4o-mini default) and cheaper/faster models for summarization (separate config) to manage cost
- C14: Anthropic-only deployments: text extraction enabled but semantic embeddings disabled (Anthropic has no embeddings API); retrieval degrades to BM25 + graph
- C15: Layer 4 (Delegation Memory), FastAPI endpoints, and framework integrations (LangGraph/CrewAI) are deferred to v2
- C16: Bi-temporal metadata stored on all Neo4j nodes/edges from day one, but only current-state queries exposed in v1 production paths; point-in-time via admin/debug command only
- C17: Proactive Redis token-bucket rate limiting per provider+model in MemoryAdapterFactory plus reactive 429/Retry-After handling with jitter — do not rely on 429-only behavior


## Acceptance Criteria

- AC1: MemoryServiceProvider registers all services, contracts, and scheduled commands gated on config('memory.enabled'); system is fully inert when disabled
- AC2: CoreMemoryManager supports CRUD for 5 block keys (agent_persona, user_profile, task_state, tool_results_cache, active_goals) with version increment, classification tagging, and AuditLogger integration
- AC3: Identity blocks (agent_persona, user_profile) accept freeform text; operational blocks (task_state, tool_results_cache, active_goals) validate against JSON schema on write
- AC4: WorkingMemoryBuffer appends to Redis sorted set, retrieves last N messages, triggers summarization when count exceeds threshold, and sets TTL via EXPIREAT on terminal status
- AC5: In no-API mode, Working Memory eviction uses oldest-first truncation (no LLM call); in API mode, uses separate summarization model with fallback to extraction model on failure
- AC6: MemoryFormationJob extracts entities, generates embeddings, persists conversation logs, and populates Neo4j graph in API mode; in no-API mode, persists conversation logs only
- AC7: MemoryFormationJob retries 5× with backoff [10,30,60,120,300]s; on exhaustion writes MemoryFormationFailure record with serialized payload for backfill
- AC8: HybridRetriever queries all available sources in parallel, applies RRF fusion (k=60), and returns partial results when any source is unavailable
- AC9: When pgvector extension is not installed, HybridRetriever skips semantic search and returns BM25 + graph results only (or BM25-only if Neo4j also unavailable)
- AC10: MemoryContextBuilder generates wrapper markdown file following TaskMarkdownStorage pattern (date subdirs, UUID filenames) with adaptive token budget (5% of runner context window, clamped to [1000..8000])
- AC11: ForgettingService implements tiered pruning: Redis TTL for Working Memory, composite decay scoring for vectors, LLM-scored importance for graph entities, with dry-run support and checkpoint-based resumable processing
- AC12: ConsolidationService runs every 2 hours via memory:consolidate, processes MemoryFormationFailure backfill, Working→Core merging, and vector deduplication with resumable checkpoints
- AC13: All memory tables enforce user_id scoping; all queries include classification-level filtering (public/internal/confidential)
- AC14: Memory Settings API (GET/PUT /memory/v1/settings) supports encrypted key storage, masked output, connection testing, and capability reporting
- AC15: MemoryCapabilityResolver correctly identifies operating mode (no-api/api/degraded) based on configured providers and pgvector availability
- AC16: Provider usage logged to memory_provider_usage table with provider, model, operation, token counts, and cost estimates; API keys never appear in any log
- AC17: memory:stats Artisan command reports per-layer counts, sizes, health status, failure backlog, and provider usage summary
- AC18: HTTP API endpoints use Sanctum auth with dedicated throttle buckets for read vs write operations, preventing memory traffic from starving job-control APIs
- AC19: MemoryAdapterFactory includes proactive Redis token-bucket rate limiter per provider+model and handles 429 responses with Retry-After + jitter
- AC20: Eight new PostgreSQL migrations run cleanly as additive changes on existing PostgreSQL 18 database (pgvector extension migration is conditional/idempotent)
- AC21: Horizon supervisor-memory configuration supports env-configurable maxProcesses (default 3) with auto-scaling strategy
- AC22: memory:graph-snapshot Artisan command provides point-in-time graph state inspection using stored bi-temporal metadata

