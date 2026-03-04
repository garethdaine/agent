# Requirements Discovery Brief — Agent Memory v3

Session: 5
Status: Ready for Implementation
Derived from: agent-memory-delegation-v2 discovery + codebase verification audit (2026-02-28)

---

## Executive Summary

Implement a four-layer memory architecture (Layers 1–3 in v1) integrated into the existing PHP 8.3 / Laravel 12 Agent Ops. The system gives agents persistent context across runs: editable identity blocks (Core Memory), short-lived conversational state (Working Memory), and durable knowledge with semantic + keyword + graph retrieval (Long-term Memory). Layer 4 (Delegation Memory) defers to v2.

Two operating modes ensure the feature is useful without any third-party API keys:
- **No-API Mode**: Core Memory + Working Memory + BM25 keyword retrieval + conversation logs. Zero LLM calls.
- **API Mode**: Full pipeline — LLM entity extraction, semantic embeddings (pgvector), Neo4j knowledge graph, hybrid RRF retrieval.

Memory formation is fully async via Horizon queues. Memory failures **never** block agent execution.

---

## What Exists Today

### Codebase Assets (verified 2026-02-28)
- **Zero memory implementation** — no models, services, controllers, migrations, config, routes, or UI components for memory
- **pgvector extension migration exists** at `database/migrations/2026_02_16_040000_enable_pgvector_extension.php` — but pgvector is NOT installed on the target PostgreSQL 18 instance
- **Adapter pattern** established in `app/Support/Interrogation/Adapters/` (ClaudeAdapter, CodexAdapter) — memory adapters will follow this
- **Settings pattern** established via `InterrogationSetting` model with `getForUser()`/`setForUser()` + encrypted storage
- **Horizon** running 4 supervisors (agent, interrogation, messenger, delegation) — memory supervisor not yet added
- **API routes** follow `/agent/api/v1/` prefix convention in `routes/api.php`
- **Redis** available (Horizon already uses it)
- **PostgreSQL 18** confirmed active
- **Vue.js settings pages** exist for Tools/Discovery/Backups — no memory settings page exists

### External Dependencies NOT Yet Installed
- `laravel/ai` — not in composer.json (planned for provider abstraction)
- `laudis/neo4j-php-client` — not in composer.json (planned for knowledge graph)
- Neo4j 5.x server — not running (new infrastructure dependency)
- pgvector system extension — not installed on PostgreSQL server

---

## Architecture: Four Memory Layers

### Layer 1 — Core Memory (Always Available)
Editable structured blocks that define agent identity and operational state. Think of these as the agent's "personality" and "working notes."

**Block Types:**
| Block Key | Type | Storage | Purpose |
|-----------|------|---------|---------|
| `agent_persona` | Identity | Freeform text (`content_text`) | Agent's personality, communication style, domain expertise |
| `user_profile` | Identity | Freeform text (`content_text`) | User preferences, context, working patterns |
| `task_state` | Operational | Schema-enforced JSON (`content_json`) | Current task context, in-progress items |
| `tool_results_cache` | Operational | Schema-enforced JSON (`content_json`) | Recent tool outputs for reuse |
| `active_goals` | Operational | Schema-enforced JSON (`content_json`) | Active objectives and priorities |

**Behaviors:**
- Self-edit via HTTP API tool calls (agent can update its own persona/profile during runs)
- Version tracking on every update (optimistic locking via version column)
- Classification tagging: `public`, `internal` (default), `confidential`
- Sleep-time consolidation merges Working Memory insights into Core blocks
- Scoped per `user_id`; operational blocks additionally scoped per `job_id`

### Layer 2 — Working Memory (Always Available)
Short-lived conversational state for the current run. Redis-backed for sub-millisecond access.

**Specification:**
- Redis sorted sets keyed by `memory:run:{run_id}:working`, scored by microsecond timestamp
- Last 15 messages retained (configurable via `MEMORY_WORKING_MAX_MESSAGES`)
- Eviction strategy depends on operating mode:
  - **No-API**: Oldest-first truncation via `ZREMRANGEBYRANK`
  - **API**: LLM summarization of oldest entries using dedicated summarization model; fallback to truncation on provider failure
- TTL: 2 hours post terminal status via Redis `EXPIREAT` (configurable via `MEMORY_WORKING_TTL`)
- Redis DB 2 (dedicated `memory` connection, isolated from Horizon's DB 0)

### Layer 3 — Long-term Memory (Hybrid, Degrades Gracefully)
Durable knowledge store combining three retrieval strategies via Reciprocal Rank Fusion.

| Component | Storage | Availability | Fallback |
|-----------|---------|-------------|----------|
| **Semantic search** | pgvector 1536d HNSW index | Requires pgvector extension + embeddings provider | Skipped; BM25 + graph only |
| **Keyword search (BM25)** | PostgreSQL GIN tsvector indexes | Always available | Always active |
| **Knowledge graph** | Neo4j 5.x Community | Requires Neo4j running | Skipped; semantic + BM25 only |
| **Conversation logs** | PostgreSQL `memory_conversation_logs` | Always available | Always populated |

**Retrieval Fusion:**
- Reciprocal Rank Fusion (RRF) with k=60
- Per-source weights configurable: `semantic_weight`, `keyword_weight`, `graph_weight` (default 1.0 each)
- Partial results returned when any source unavailable (never throws)
- Classification filtering enforced on all queries

---

## Operating Modes

### No-API Mode (Zero Provider Keys)
| Layer | Behavior |
|-------|----------|
| Core Memory | Full CRUD, version tracking, self-edit |
| Working Memory | Redis sorted sets, oldest-first truncation eviction |
| Long-term: Conv Logs | Always persisted |
| Long-term: Embeddings | Skipped (no embeddings provider) |
| Long-term: Graph | Skipped (no LLM extraction) |
| Retrieval | BM25-only via PostgreSQL tsvector |

### API Mode (Provider Keys Configured + `api_features_enabled`)
All No-API features PLUS:
- LLM entity extraction (GPT-4o-mini default)
- LLM importance scoring
- Semantic embeddings (text-embedding-3-small default)
- Neo4j knowledge graph population
- LLM summarization for Working Memory eviction (separate cheaper model)
- Full hybrid RRF retrieval

### Anthropic-Only Degraded Mode
When only Anthropic API key configured:
- Text extraction enabled (entity extraction, summarization)
- Semantic embeddings **disabled** (Anthropic has no embeddings API)
- Retrieval: BM25 + graph traversal only (no semantic)
- `/memory/v1/settings/capabilities` reports `degraded` mode

### Mode Gating
- `MEMORY_ENABLED` (master toggle, default `false`)
- `MEMORY_API_ENABLED` (LLM/embedding toggle, default `false`)
- `MemoryCapabilityResolver` auto-detects mode at runtime by inspecting:
  1. Configured provider keys in `memory_settings`
  2. pgvector extension availability (`SELECT 1 FROM pg_extension WHERE extname='vector'`)
  3. Neo4j connectivity

---

## Memory Formation (Async, Non-Blocking)

### Dual-Tap Architecture
Memory is formed through two independent, async pathways:

**Tap 1 — Working Memory (Streaming)**
- **Trigger**: `RunEventWriter::appendOutput()` dispatches `MemoryWorkingBufferJob` after each output chunk
- **Latency**: <1ms (Redis ZADD only, fire-and-forget)
- **Retry**: 0 (silent failure — zero backpressure on 250ms poll loop)
- **Integration point**: `app/Support/Agent/RunEventWriter.php` after `persistRunStats()` (line 52)
- **Guard**: `config('memory.enabled')`, wrapped in isolated try/catch

**Tap 2 — Long-term Memory (Post-Run Batch)**
- **Trigger**: `ExecuteAgentRunJob::finalizeTerminal()` dispatches `MemoryFormationJob` on terminal status
- **Retry**: 5× with exponential backoff [10s, 30s, 60s, 120s, 300s]
- **Failure**: Exhausted retries write `MemoryFormationFailure` record with serialized payload for backfill
- **Integration point**: `app/Jobs/ExecuteAgentRunJob.php` after `applyPathFailurePolicy()` (line 379)
- **Guard**: `config('memory.enabled')`, wrapped in try/catch — memory never blocks finalization

### Formation Pipeline (API Mode)
1. Retrieve Working Memory buffer for the completed run
2. Persist conversation log entries to `memory_conversation_logs`
3. Extract entities via `ExtractionProvider::extractEntities()`
4. Score importance via `ExtractionProvider::scoreImportance()`
5. Generate embeddings via `EmbeddingProvider::embed()`
6. Persist embeddings to `memory_embeddings` (with content_hash dedup)
7. Store entities/relationships in Neo4j via `Neo4jGraphStore`
8. Handle partial failures: if any step fails, persist what succeeded + record failure

---

## Context Injection

### Mechanism
`MemoryContextBuilder` generates a wrapper markdown file that prepends memory context to the agent's task instructions:

```
## Agent Identity
{core_memory.agent_persona}

## User Context
{core_memory.user_profile}

## Relevant Memories
{hybrid_retrieval_results — ranked by RRF, truncated to budget}

---
{original_task_content}
```

### Integration
- **Hook point**: `ExecuteAgentRunJob::handle()` between run loading (line 43) and `renderTokens()` (line 91)
- Override `$run->job->task_markdown_path` transiently (not persisted to DB)
- Does NOT modify `CommandTemplateRenderer`
- File follows `TaskMarkdownStorage` pattern: date subdirs, UUID filenames, under `memory/context/` prefix
- Path added to `allowed_task_markdown_bases` in `config/agent.php`

### Token Budget
- Adaptive: 5% of runner's context window (from runner config)
- Hard floor: 1,000 tokens
- Hard ceiling: 8,000 tokens
- Clamped to [1000..8000] range
- Token approximation: 4 chars/token (conservative estimate, acceptable for v1)
- If retrieved context exceeds budget: summarize or truncate

### Environment Injection
- `MEMORY_API_BASE_URL` injected in `ExecuteAgentRunJob::mergedEnvironment()` after line 298
- Value: `config('app.url') . '/agent/api/v1/memory'`
- Programmatic injection — bypasses `EnvPolicy.forbidden_env_key_pattern`
- Enables the agent process to call memory API endpoints during execution

---

## Consolidation & Forgetting

### Consolidation (Every 2 Hours + Event-Driven)
| Operation | Description |
|-----------|-------------|
| Working → Core merge | Summarize Working Memory insights into Core blocks (API mode only) |
| Vector deduplication | Identify near-duplicate embeddings via cosine similarity, merge metadata |
| Failure backfill | Retry failed formations from `MemoryFormationFailure` with serialized payloads |
| Checkpoint | Resumable processing following `AgentMaintenanceCheckpoint` pattern |

### Forgetting Policy (Tiered)
| Layer | Strategy | Threshold |
|-------|----------|-----------|
| Working Memory | Redis TTL | 2 hours post terminal status |
| Vector embeddings | Composite decay: `importance × recency_decay × access_frequency_bonus` | Prune below 0.1 |
| Graph entities | LLM-scored importance (API) or age-based (no-API) | Soft-delete below 0.2 after 90-day retention |
| Conversation logs | Time-based retention | Configurable days |

### Scheduled Commands
| Command | Schedule | Purpose |
|---------|----------|---------|
| `memory:consolidate` | Every 2 hours (`0 */2 * * *`) | Consolidation + failure backfill |
| `memory:prune` | Daily at 03:30 | Tiered forgetting with dry-run support |
| `memory:stats` | Manual | Per-layer diagnostics + provider usage |
| `memory:graph-snapshot` | Manual/Admin | Point-in-time graph inspection using bi-temporal metadata |

---

## Provider Abstraction

### Architecture
- **Laravel AI SDK** (`laravel/ai`) for multi-provider support — replaces custom HTTP clients
- Two contracts: `EmbeddingProvider` and `ExtractionProvider`
- `MemoryAdapterFactory` resolves implementations based on Memory Settings
- Failover chains per capability type

### Three Model Tiers
| Tier | Default Provider | Default Model | Purpose |
|------|-----------------|---------------|---------|
| Extraction | OpenAI | gpt-4o-mini | Entity extraction, importance scoring |
| Summarization | OpenAI | gpt-4.1-nano | Working Memory eviction, context truncation |
| Embeddings | OpenAI | text-embedding-3-small | Semantic vector generation (1536d) |

### Rate Limiting
- Proactive Redis token-bucket per provider+model key
- Reactive 429/Retry-After handling with jitter
- Rate limit config per provider in `config/memory.php`

### Failover
- Text extraction: fails over across text-capable providers (OpenAI ↔ Anthropic)
- Embeddings: fails over only across embeddings-capable providers; never to text-only
- Summarization: falls back to extraction model if summarization model unavailable

---

## Data Model (8 New PostgreSQL Migrations)

### memory_settings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint nullable FK → users | NULL = workspace default |
| key | varchar(100) | unique per user_id |
| value | text | Laravel `encrypted` cast |
| created_at / updated_at | timestamptz | |

**Setting Keys:** `memory_enabled`, `api_features_enabled`, `extraction_provider`, `extraction_model`, `summarization_provider`, `summarization_model`, `embeddings_provider`, `embeddings_model`, `provider_key_openai` (encrypted), `provider_key_anthropic` (encrypted), `embedding_dimensions`

### memory_core_blocks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK NOT NULL → users | |
| job_id | bigint nullable FK → agent_jobs | operational blocks |
| block_type | varchar(20) | 'identity' or 'operational' |
| block_key | varchar(100) | 5 known keys |
| content_text | text nullable | freeform identity blocks |
| content_json | jsonb nullable | schema-enforced operational blocks |
| classification | varchar(20) default 'internal' | |
| version | integer default 1 | auto-increment on update |
| created_at / updated_at | timestamptz | |

**Indexes:** `unique(user_id, block_key, job_id)`, `idx(user_id, classification)`

### memory_embeddings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK NOT NULL → users | |
| source_type | varchar(30) | 'conversation', 'extraction', 'summary' |
| source_id | varchar(100) | polymorphic reference |
| content | text | original text embedded |
| content_hash | varchar(64) | SHA-256 dedup |
| embedding | vector(1536) nullable | pgvector; null when unavailable |
| metadata_json | jsonb nullable | |
| classification | varchar(20) default 'internal' | |
| importance_score | double precision default 0.5 | 0.0–1.0 |
| access_count | integer default 0 | |
| last_accessed_at | timestamptz nullable | |
| created_at | timestamptz | immutable |

**Indexes:** `HNSW(embedding vector_cosine_ops, m=16, ef_construction=128)` (conditional on pgvector), `GIN(to_tsvector('english', content))`, `idx(user_id, classification)`, `idx(content_hash)`

### memory_conversation_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | bigint FK → users | |
| run_id | bigint FK → agent_job_runs | |
| job_id | bigint FK → agent_jobs | |
| role | varchar(20) | 'system', 'user', 'assistant', 'tool' |
| content | text | message content |
| sequence | integer | order within run |
| event_type | varchar(20) | 'stdout', 'stderr', 'lifecycle' |
| classification | varchar(20) default 'internal' | |
| created_at | timestamptz | immutable |

**Indexes:** `idx(user_id, run_id, sequence)`, `idx(user_id, classification)`, `GIN(to_tsvector('english', content))`

### memory_consolidation_log
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint nullable FK | |
| consolidation_type | varchar(30) | 'working_to_core', 'dedup_vectors', 'graph_prune', 'backfill' |
| source_count | integer | items processed |
| result_summary | text nullable | |
| checkpoint_json | jsonb nullable | resumable state |
| created_at | timestamptz | immutable |

### memory_formation_failures
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| run_id | bigint FK → agent_job_runs | |
| user_id | bigint FK → users | |
| failure_type | varchar(30) | 'embedding', 'graph_extraction', 'conversation_log', 'full' |
| error_message | text | |
| attempts | integer | max 5 |
| backfilled_at | timestamptz nullable | set on recovery |
| payload_json | jsonb | serialized context for retry |
| created_at | timestamptz | immutable |

**Indexes:** Partial index `WHERE backfilled_at IS NULL`

### memory_provider_usage
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK → users | |
| run_id | bigint nullable FK → agent_job_runs | |
| provider | varchar(30) | 'openai', 'anthropic' |
| model | varchar(100) | |
| operation | varchar(30) | 'embedding', 'extraction', 'summarization', 'importance_scoring' |
| input_tokens | integer | |
| output_tokens | integer nullable | null for embeddings |
| cost_estimate_usd | double precision nullable | |
| created_at | timestamptz | immutable |

### pgvector extension
Conditional migration: `CREATE EXTENSION IF NOT EXISTS vector;` — non-fatal, system degrades to BM25+graph when unavailable.

---

## HTTP API (12 Endpoints)

All endpoints under `/agent/api/v1/memory/` — Sanctum-guarded, dedicated throttle buckets.

### Settings Management
| Method | Route | Purpose | Throttle |
|--------|-------|---------|----------|
| GET | `/memory/settings` | Read all settings (keys masked) | reads (120/min) |
| PUT | `/memory/settings` | Update settings (batch) | writes (30/min) |
| POST | `/memory/settings/test-connection` | Test provider connectivity | writes |
| GET | `/memory/settings/capabilities` | Operating mode + features | reads |

### Core Blocks CRUD
| Method | Route | Purpose | Throttle |
|--------|-------|---------|----------|
| GET | `/memory/core-blocks` | List blocks for auth user | reads |
| GET | `/memory/core-blocks/{key}` | Read single block | reads |
| PUT | `/memory/core-blocks/{key}` | Create/update block | writes |
| DELETE | `/memory/core-blocks/{key}` | Soft-delete block | writes |

### Retrieval & Working Memory
| Method | Route | Purpose | Throttle |
|--------|-------|---------|----------|
| POST | `/memory/retrieve` | Hybrid retrieval query | reads |
| POST | `/memory/working/append` | Append to working memory | writes |
| GET | `/memory/working/{runId}` | Read working memory for run | reads |

### Diagnostics
| Method | Route | Purpose | Throttle |
|--------|-------|---------|----------|
| GET | `/memory/stats` | Diagnostics + provider usage | reads |

---

## Access Control

### Classification Levels
- `public` — accessible across all contexts
- `internal` (default) — standard access, user-scoped
- `confidential` — restricted, explicit query required

### Enforcement
- All PostgreSQL queries include `WHERE classification IN (...)` filter
- All Neo4j Cypher queries include classification `MATCH` constraint
- Application-level enforcement (Neo4j Community has no built-in RBAC)
- Per-user scoping: all memory operations scoped to `auth()->id()`

### Security
- Provider API keys encrypted at rest via Laravel `encrypted` cast (AES-256-CBC via APP_KEY)
- Keys masked in all API responses (show last 4 chars: `sk-...a1b2`)
- Key values NEVER logged in audit trail
- All memory mutations logged via existing `AuditLogger::recordSystemAction()`
- New audit target_types: `memory_core_block`, `memory_embedding`, `memory_graph_entity`, `memory_consolidation`, `memory_prune`, `memory_settings`

---

## Integration Points (Existing Code Modifications)

### Files Modified (~10)
| File | Change | Lines |
|------|--------|-------|
| `bootstrap/providers.php` | Add `MemoryServiceProvider` | append |
| `config/database.php` | Add Redis `memory` connection (DB 2) | after line 179 |
| `config/horizon.php` | Add `supervisor-memory` queue + waits | after line 228 |
| `config/agent.php` | Add memory context path to `allowed_task_markdown_bases` | line 30 |
| `composer.json` | Add `laravel/ai` + `laudis/neo4j-php-client` | require section |
| `routes/console.php` | Schedule `memory:consolidate` + `memory:prune` | append |
| `routes/api.php` | Add `memory/` sub-group inside existing `agent/api/v1` prefix | line 16 area |
| `app/Jobs/ExecuteAgentRunJob.php` | 3 insertions: context builder (handle), formation job (finalizeTerminal), env injection (mergedEnvironment) | lines 43-91, 298, 379 |
| `app/Support/Agent/RunEventWriter.php` | Working buffer job dispatch in appendOutput() | after line 52 |
| `app/Providers/AppServiceProvider.php` | Memory rate limiter definitions | boot() |

### New Files (~43)
| Category | Count | Location |
|----------|-------|----------|
| Config | 1 | `config/memory.php` |
| Migrations | 8 | `database/migrations/2026_02_*` |
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

---

## Gap Analysis — Issues Found in v2 Discovery

### GAP-1: `laravel/ai` Package Viability (CRITICAL)
**Issue**: The v2 discovery specifies `laravel/ai` as the provider abstraction layer, but this package may not be production-ready or may not exist in the expected form. It is not in composer.json or lock file.
**Resolution Required**: Before Phase 3 implementation, verify the package exists at the expected quality level. If unavailable or immature, fall back to direct HTTP client calls wrapped behind the same `EmbeddingProvider`/`ExtractionProvider` contracts. The adapter pattern isolates this decision.
**Recommendation**: Build contracts first (Phase 2), then evaluate `laravel/ai` vs direct HTTP in Phase 3. The contract-first approach means the provider implementation is swappable.

### GAP-2: Neo4j as New Infrastructure Dependency (HIGH) — RESOLVED
**Decision**: Neo4j is **required** for v1. Deployment via **Docker Compose** service for both local dev and production.
**Resolved Actions**:
- Add Neo4j 5.x LTS Community as a Docker Compose service in `docker-compose.yml`
- Configure bolt://neo4j:7687 (container networking) as default connection
- Add health check in Docker Compose (`neo4j status` or HTTP 7474 check)
- Add `memory:check-neo4j` Artisan command for connectivity verification
- Document Neo4j container setup in deployment docs
- Backup strategy: Neo4j `neo4j-admin database dump` via scheduled container exec

### GAP-3: Vue.js Memory Settings UI (MEDIUM)
**Issue**: The discovery docs specify HTTP API endpoints but make no mention of a Vue.js settings page. Every other feature (Discovery, Interrogation, Messenger) has a Settings.vue page. Memory settings will need one for provider configuration.
**Required**: Specification for a `Pages/Tools/Memory/Settings.vue` component that allows users to configure provider keys, test connections, view capabilities, and monitor usage.
**Recommendation**: Add a Phase 7b for the Vue.js settings UI following the existing Discovery/Settings.vue pattern.

### GAP-4: User Deletion / Privacy Cleanup (MEDIUM)
**Issue**: No mention of what happens to memory data when a user is deleted. GDPR and data privacy require a clear deletion path.
**Required**: Cascading delete or anonymization strategy for all memory tables when a user account is removed.
**Recommendation**: Add `ON DELETE CASCADE` on all `user_id` foreign keys for memory tables. Add a `memory:purge-user {userId}` command for explicit cleanup including Neo4j graph data.

### GAP-5: Concurrent Run Handling (MEDIUM)
**Issue**: If a user has multiple runs executing simultaneously, Working Memory buffers are per-run (correct), but Long-term Memory formation could have race conditions on embedding dedup (content_hash) and graph entity creation.
**Required**: Document concurrency behavior. Formation jobs for the same user running in parallel should not create duplicate embeddings or conflicting graph entities.
**Recommendation**: Use `content_hash` uniqueness constraint with `INSERT ... ON CONFLICT DO NOTHING` for embeddings. Neo4j `MERGE` for entity creation (already idempotent).

### GAP-6: Embedding Model Migration Path (LOW)
**Issue**: If the user changes embedding models (e.g., from text-embedding-3-small to a different model with different dimensions), existing embeddings become incompatible.
**Required**: Strategy for handling embedding model changes.
**Recommendation**: v1 — document that changing models invalidates existing embeddings. Add `memory:reindex` command to v1.1 roadmap for re-embedding all content.

### GAP-7: Core Block JSON Schema Definitions (LOW)
**Issue**: Operational blocks (task_state, tool_results_cache, active_goals) require JSON schema validation, but the actual schemas are not specified in the discovery doc.
**Required**: Concrete JSON schemas for each operational block type.
**Recommendation**: Define minimal schemas during Phase 2 implementation. Start permissive (allow additional properties) and tighten over time.

### GAP-8: Graph Entity Extraction Prompt Engineering (LOW)
**Issue**: The `ExtractionProvider::extractEntities()` interface is defined, but the actual prompts for entity extraction are not specified. Quality of graph data depends heavily on prompt design.
**Required**: Entity extraction prompt template that produces consistent, structured output.
**Recommendation**: Include prompt templates in `config/memory.php` or as dedicated prompt files in `resources/prompts/memory/`. Design during Phase 5 with iteration on real agent output data.

---

## Resolved Architectural Decisions (22)

| # | Decision | Resolution |
|---|----------|------------|
| D1 | Runtime | PHP 8.3 + Laravel 12 integrated, not separate service |
| D2 | Identity/Ownership | Hybrid: Core/Long-term per user_id; Working per run_id; operational per job_id |
| D3 | Formation Taps | Dual: RunEventWriter for Working, ExecuteAgentRunJob for Long-term |
| D4 | LLM/Embedding | Laravel AI SDK + adapter pattern (verify package viability — see GAP-1) |
| D5 | Storage | PostgreSQL 18 + pgvector 0.8.1 (when installed) + Neo4j 5.x LTS Community (required, Docker Compose) + Redis DB 2 |
| D6 | Core Blocks | Hybrid: freeform text (identity) + schema JSON (operational), version tracked |
| D7 | Working Memory | Redis sorted sets, last 15 messages, 2h TTL |
| D8 | Long-term Memory | pgvector + Neo4j + conversation logs with RRF retrieval |
| D9 | Forgetting | Tiered: TTL → decay scoring → importance → time-based |
| D10 | Retrieval Fusion | RRF with k=60, configurable per-source weights |
| D11 | Consolidation | Event-driven + every 2h, checkpoint-resumable |
| D12 | Access Control | Classification levels (public/internal/confidential), app-level enforcement |
| D13 | V1 Scope | Layers 1–3; Layer 4 deferred |
| D14 | Context Injection | Wrapper file, adaptive budget 5% clamped [1K..8K] |
| D15 | Error Handling | Retry 5× with backoff, MemoryFormationFailure for backfill, never blocks |
| D16 | Infra Versions | PostgreSQL 18, pgvector 0.8.1 target, Neo4j 5.x LTS Community |
| D17 | Provider Capability | Auto-detect text_generation and/or embeddings |
| D18 | Mode Gating | MEMORY_ENABLED + MEMORY_API_ENABLED |
| D19 | Provider Failover | Text: cross-provider; Embeddings: same-capability only |
| D20 | Bi-Temporal Graphs | Store from day one; current-state queries v1; temporal v1.1 |
| D21 | Run Volume | <20/day; 3 workers default; env-configurable |
| D22 | Env Injection | MEMORY_API_BASE_URL programmatic, bypasses EnvPolicy |

---

## Constraints

- C1: PHP 8.3 + Laravel 12 integrated — no separate services
- C2: pgvector is deployment prerequisite but NOT installed; graceful degradation to BM25
- C3: Neo4j 5.x LTS Community — required for v1; Docker Compose deployment; app-level access control
- C4: Memory failures NEVER block agent runs (async-first)
- C5: Volume <20 runs/day; 3 Horizon workers default
- C6: Provider config via Memory Settings + adapter resolution; no credential duplication in config/services.php
- C7: All API keys encrypted at rest; masked in output; never logged
- C8: Per-user scoping only in v1; schema forward-compatible for scope_type/scope_id
- C9: Additive migrations only (already on PostgreSQL)
- C10: WorkingBufferJob: fire-and-forget, zero backpressure on 250ms poll loop
- C11: Context injection does NOT modify CommandTemplateRenderer
- C12: MEMORY_API_BASE_URL injected programmatically, bypasses EnvPolicy
- C13: Medium-tier models for extraction; cheaper models for summarization
- C14: Anthropic-only: text extraction yes, embeddings no
- C15: Layer 4, FastAPI, LangGraph/CrewAI integrations deferred to v2
- C16: Bi-temporal stored from day one; current-state queries only in v1
- C17: Proactive rate limiting + reactive 429 handling

---

## Acceptance Criteria

- AC1: MemoryServiceProvider registers all services gated on `config('memory.enabled')`; system fully inert when disabled
- AC2: CoreMemoryManager CRUD for 5 block keys with version increment, classification, audit logging
- AC3: Identity blocks accept freeform text; operational blocks validate JSON schema on write
- AC4: WorkingMemoryBuffer: append, retrieve last N, summarization trigger, TTL on terminal
- AC5: No-API eviction: oldest-first truncation; API eviction: LLM summarization with fallback
- AC6: MemoryFormationJob: entities + embeddings + conv logs + graph (API mode); conv logs only (no-API)
- AC7: Formation retry 5× with backoff; exhausted → MemoryFormationFailure record
- AC8: HybridRetriever: parallel source queries, RRF fusion, partial results
- AC9: Missing pgvector → skip semantic, return BM25 + graph; Neo4j required and expected available
- AC10: MemoryContextBuilder: wrapper file with adaptive budget clamped [1000..8000]
- AC11: ForgettingService: tiered pruning with dry-run support
- AC12: ConsolidationService: every 2h, backfill, dedup, checkpoint-resumable
- AC13: All tables enforce user_id scoping; classification filtering on all queries
- AC14: Settings API: encrypted storage, masked output, connection testing, capabilities
- AC15: MemoryCapabilityResolver: correct mode detection (no-api/api/degraded)
- AC16: Provider usage tracked in memory_provider_usage; keys never in logs
- AC17: memory:stats reports per-layer diagnostics
- AC18: API endpoints: Sanctum auth, dedicated throttle buckets
- AC19: Rate limiter: proactive token-bucket + reactive 429 with jitter
- AC20: 8 migrations run cleanly; pgvector migration idempotent/non-fatal
- AC21: Horizon supervisor-memory: env-configurable, auto-scaling
- AC22: memory:graph-snapshot: point-in-time inspection via bi-temporal metadata
- AC23: (NEW) Vue.js Memory Settings page for provider configuration and usage monitoring
- AC24: (NEW) User deletion cascades to all memory tables + Neo4j cleanup
- AC25: (NEW) Concurrent formation jobs for same user handle dedup correctly

---

## Implementation Phases (8 Phases, ~25 Working Days)

| Phase | Name | Prereq | Risk | Days |
|-------|------|--------|------|------|
| 1 | Foundation: config, migrations, models, provider | None | Low | 3-4 |
| 2 | Core Memory (Layer 1): CoreMemoryManager + Settings | Phase 1 | Low | 3-4 |
| 3 | Provider Abstraction: contracts, adapters, rate limiting | Phase 2 | Medium | 3-4 |
| 4 | Working Memory (Layer 2): Redis buffer + summarization | Phase 3 | Low | 2-3 |
| 5 | Long-term Memory (Layer 3): embeddings, graph, conv logs | Phase 3+4 | High | 5-6 |
| 6 | Consolidation + Forgetting + Artisan commands | Phase 5 | Medium | 3-4 |
| 7 | Context Injection + HTTP API + Vue Settings UI | Phase 5 | Medium | 4-5 |
| 8 | Integration testing + performance validation | Phase 7 | Low | 2-3 |

---

## Risks

| # | Risk | Impact | Mitigation |
|---|------|--------|-----------|
| R1 | laravel/ai SDK maturity | Provider integration blocked | Contract-first; swap to direct HTTP if needed |
| R2 | pgvector not installed | No semantic search | Graceful degradation to BM25; non-fatal migration |
| R3 | Neo4j Community limitations | No RBAC, no HA | App-level access control; optional for v1 |
| R4 | Neo4j operational complexity | New infra dependency | Docker Compose simplifies ops; `memory:check-neo4j` health command; container restart policy |
| R5 | RunEventWriter hot path sensitivity | Poll loop degradation | Fire-and-forget, <1ms Redis, isolated try/catch |
| R6 | ExecuteAgentRunJob modification | Run execution failure | All guarded by config + try/catch; instant rollback via flag |
| R7 | Token budget approximation | Over/under injection | Conservative 4:1 ratio + 10% margin + hard ceiling |
| R8 | Provider cost accumulation | Unexpected spend | Medium-tier models, separate summarization config, usage tracking |
| R9 | Async formation data loss | Incomplete memory | MemoryFormationFailure table + 2h consolidation backfill |
| R10 | APP_KEY rotation | Encrypted settings invalidated | Document procedure; re-encryption command for v1.1 |

---

## Assumptions

- A1: PostgreSQL 18 active (confirmed)
- A2: Redis available (confirmed, Horizon uses it)
- A3: pgvector not installed; system degrades gracefully
- A4: Neo4j 5.x will be deployed alongside existing stack (or feature disabled)
- A5: laravel/ai stable enough (or direct HTTP fallback used)
- A6: Volume <20 runs/day; 3 memory workers sufficient
- A7: APP_KEY stable during deployment
- A8: Follow InterrogationSetting/AdapterFactory/MaintenancePruneService patterns
- A9: Per-user only in v1
- A10: Layer 4 deferred to v2
- A11: 4 chars/token approximation acceptable
- A12: Horizon has capacity for additional supervisor
- A13: API routes follow `/agent/api/v1/` convention
