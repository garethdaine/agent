# Requirements Discovery Summary

Session: 14

# Agent Memory v3 — Implementation-Ready Specification

## Overview
Four-layer memory architecture (Layers 1–3 in v1, Layer 4 deferred to v2) integrated into existing PHP 8.3 / Laravel 12 Agent Scheduler. Provides agents with persistent context across runs: editable identity blocks (Core Memory), short-lived conversational state (Working Memory), and durable knowledge with semantic + keyword + graph retrieval (Long-term Memory).

## Operating Modes

### No-API Mode (Zero Provider Keys)
- Core Memory: Full CRUD, version tracking
- Working Memory: Redis sorted sets, oldest-first truncation eviction
- Long-term: Conversation logs always persisted; embeddings and graph skipped
- Retrieval: BM25-only via PostgreSQL tsvector

### API Mode (Provider Keys + `api_features_enabled`)
All No-API features plus:
- LLM entity extraction (GPT-4o-mini default)
- LLM importance scoring
- Semantic embeddings (text-embedding-3-small, fixed 1536d)
- Neo4j knowledge graph population
- LLM summarization for Working Memory eviction
- Full hybrid RRF retrieval

### Anthropic-Only Degraded Mode
- Text extraction enabled (entity extraction, summarization)
- Semantic embeddings disabled (Anthropic has no embeddings API)
- Retrieval: BM25 + graph traversal only

## Architecture Decisions

### Infrastructure
- **Neo4j**: Required for v1; Docker Compose service for both local dev and production (containerized Neo4j 5.x Community)
- **pgvector**: 1536-dimension embeddings only for v1; graceful degradation to BM25 when extension unavailable
- **Redis**: DB 2 for memory (isolated from Horizon's DB 0)
- **WebSockets**: Laravel Reverb for real-time diagnostics (self-hosted, no external dependencies)

### Provider Abstraction
- Primary: Attempt `laravel/ai` package (branch 0.x)
- Fallback: Direct HTTP clients (Guzzle) wrapped behind `EmbeddingProvider`/`ExtractionProvider` contracts
- Three model tiers: Extraction (gpt-4o-mini), Summarization (gpt-4.1-nano), Embeddings (text-embedding-3-small)
- Cost estimation via hardcoded pricing tables in `config/memory.php`

### Data Retention
- Conversation logs: Unlimited retention by default (no auto-purge); users manage via `memory:prune` command
- Vector embeddings: Composite decay scoring, prune below 0.1 threshold
- Graph entities: Soft-delete below 0.2 importance after 90-day retention
- Working Memory: 2-hour TTL post terminal status

### Horizon Queue Strategy
- `memory-working` queue: High priority, fire-and-forget for Working Memory buffer jobs
- `memory-formation` queue: Normal priority, retryable for Long-term Memory formation jobs
- Default 3 workers per queue, env-configurable

## Layer 1 — Core Memory

### Block Types
| Block Key | Type | Storage | Agent Editable |
|-----------|------|---------|----------------|
| `agent_persona` | Identity | Freeform text | No (user-only) |
| `user_profile` | Identity | Freeform text | No (user-only) |
| `task_state` | Operational | JSON (permissive) | Yes |
| `tool_results_cache` | Operational | JSON (permissive) | Yes |
| `active_goals` | Operational | JSON (permissive) | Yes |

### JSON Schema Validation
- v1: Fully permissive — accept any valid JSON for operational blocks
- v1.1: Add strict schema validation after observing real usage patterns

### Classification Control
- Three levels: `public`, `internal` (default), `confidential`
- Automatic default: `internal`
- Agents: Can elevate to `confidential`, cannot downgrade
- Users: Can set any level via UI

## Layer 2 — Working Memory

### Message Boundaries
- Per logical turn: Aggregate all output until a tool call or user input boundary
- Last 15 logical turns retained (configurable via `MEMORY_WORKING_MAX_MESSAGES`)

### Eviction Strategy
- No-API Mode: Oldest-first truncation via `ZREMRANGEBYRANK`
- API Mode: LLM summarization of oldest entries; fallback to truncation on provider failure

## Layer 3 — Long-term Memory

### Knowledge Graph Entity Types
Standard NER: Person, Organization, Location, Date, Concept
Technical entities: File, Function, Class, API, Error, Dependency

### Retrieval Fusion
- Reciprocal Rank Fusion (RRF) with k=60
- Configurable per-source weights: `semantic_weight`, `keyword_weight`, `graph_weight`

### Formation Failure Handling
- Initial job: 5 retries with exponential backoff [10s, 30s, 60s, 120s, 300s]
- Consolidation backfill: 5 cycles (10 hours total) before marking permanently unrecoverable

## Context Injection

### Token Budget
- Base: 5% of runner's context window
- Approximation: 4 chars/token
- Safety margin: 10% reduction after calculation
- Hard floor: 1,000 tokens
- Hard ceiling: 8,000 tokens

## Vue.js Settings Page

### Scope: Expert Level
- Provider API key management (encrypted storage, masked display)
- Model selection for extraction, summarization, embeddings
- Connection testing for all providers and Neo4j
- Rate limit configuration per provider
- Token budget settings
- Forgetting threshold controls (decay parameters, retention periods)
- Real-time diagnostics via Laravel Reverb WebSocket:
  - Formation job progress
  - Consolidation progress
  - Provider usage metrics
  - Cost tracking

## Artisan Commands

| Command | Default Behavior | Flags |
|---------|------------------|-------|
| `memory:consolidate` | Execute consolidation | — |
| `memory:prune` | Dry-run (preview only) | `--force` or `--execute` to actually delete |
| `memory:stats` | Display diagnostics | — |
| `memory:graph-snapshot` | Point-in-time inspection | — |
| `memory:purge-user {userId}` | Cascade delete all user memory + Neo4j cleanup | — |

## Database Migrations

### Ordering (Sequential 1-minute gaps)
1. `memory_settings` — no FKs
2. `memory_core_blocks` — FK to users, agent_jobs
3. `memory_embeddings` — FK to users
4. `memory_conversation_logs` — FK to users, agent_job_runs, agent_jobs
5. `memory_consolidation_log` — FK to users (nullable)
6. `memory_formation_failures` — FK to users, agent_job_runs
7. `memory_provider_usage` — FK to users, agent_job_runs (nullable)
8. `enable_pgvector_extension` — conditional, non-fatal

### Foreign Key Behavior
- All `user_id` FKs: `ON DELETE CASCADE` for GDPR compliance
- `content_hash` uniqueness: `INSERT ... ON CONFLICT DO NOTHING` for concurrent dedup
- Neo4j entities: `MERGE` for idempotent creation

## New Files Summary

### Configuration
- `config/memory.php` — all settings, rate limits, pricing tables, entity types

### Models (7)
- `MemorySetting`, `MemoryCoreBlock`, `MemoryEmbedding`, `MemoryConversationLog`, `MemoryConsolidationLog`, `MemoryFormationFailure`, `MemoryProviderUsage`

### Services (11)
- `CoreMemoryManager`, `WorkingMemoryBuffer`, `MemoryFormationPipeline`, `HybridRetriever`, `MemoryContextBuilder`, `ConsolidationService`, `ForgettingService`, `MemoryCapabilityResolver`, `Neo4jGraphStore`, `MemoryAdapterFactory`, `ProviderUsageTracker`

### Contracts (2)
- `EmbeddingProvider`, `ExtractionProvider`

### Adapters (3)
- `OpenAIAdapter`, `AnthropicAdapter`, `GuzzleHttpAdapter` (fallback)

### Jobs (4)
- `MemoryWorkingBufferJob`, `MemoryFormationJob`, `MemoryConsolidationJob`, `MemoryPruneJob`

### Commands (4)
- `MemoryConsolidateCommand`, `MemoryPruneCommand`, `MemoryStatsCommand`, `MemoryGraphSnapshotCommand`

### Controllers (5)
- `MemorySettingsController`, `MemoryCoreBlockController`, `MemoryRetrievalController`, `MemoryWorkingController`, `MemoryDiagnosticsController`

## Modified Files

| File | Change |
|------|--------|
| `bootstrap/providers.php` | Add `MemoryServiceProvider` |
| `config/database.php` | Add Redis `memory` connection (DB 2) |
| `config/horizon.php` | Add `supervisor-memory-working` and `supervisor-memory-formation` |
| `config/agent.php` | Add memory context path to `allowed_task_markdown_bases` |
| `config/broadcasting.php` | Configure Laravel Reverb |
| `composer.json` | Add `laravel/ai` (branch 0.x), `laudis/neo4j-php-client`, `laravel/reverb` |
| `docker-compose.yml` | Add Neo4j 5.x service |
| `routes/console.php` | Schedule `memory:consolidate` (every 2h), `memory:prune` (daily 03:30 with --force) |
| `routes/api.php` | Add `memory/` sub-group |
| `routes/channels.php` | Add memory broadcast channels |
| `app/Jobs/ExecuteAgentRunJob.php` | Context injection, formation job dispatch, env injection |
| `app/Support/Agent/RunEventWriter.php` | Working buffer job dispatch |
| `app/Providers/AppServiceProvider.php` | Memory rate limiter definitions |

## Goals

- Implement four-layer memory architecture (Layers 1-3) integrated into PHP 8.3 / Laravel 12 Agent Scheduler
- Enable agents to maintain persistent context across runs via Core Memory (identity + operational blocks)
- Provide short-lived conversational state via Redis-backed Working Memory with 15-message logical turn retention
- Build hybrid Long-term Memory retrieval combining pgvector semantic search, PostgreSQL BM25 keyword search, and Neo4j knowledge graph with RRF fusion
- Support two operating modes: No-API Mode (BM25 + conversation logs only) and full API Mode (embeddings + graph + entity extraction)
- Deploy Neo4j 5.x Community as containerized Docker service for knowledge graph storage
- Create Expert-level Vue.js settings page with real-time WebSocket diagnostics via Laravel Reverb
- Implement async memory formation via dual Horizon queues (memory-working, memory-formation) that never blocks agent execution
- Extract technical entities (Person, Organization, Location, Date, Concept, File, Function, Class, API, Error, Dependency) for knowledge graph
- Build provider abstraction layer with EmbeddingProvider/ExtractionProvider contracts supporting laravel/ai or Guzzle fallback
- Enable context injection via wrapper markdown file with adaptive token budget (5% of context window, 10% margin, clamped 1000-8000)
- Implement tiered forgetting with consolidation every 2 hours and daily pruning
- Track provider usage and costs via hardcoded pricing tables in config
- Ensure GDPR compliance via ON DELETE CASCADE on all user_id foreign keys plus memory:purge-user command


## Constraints

- PHP 8.3 + Laravel 12 integrated — no separate services
- Neo4j required for v1 — knowledge graph is essential for memory usefulness
- Neo4j deployed as Docker Compose service for both local dev and production
- pgvector graceful degradation to BM25 when extension unavailable
- Fixed 1536-dimension embeddings only for v1 (text-embedding-3-small compatible)
- Memory failures NEVER block agent runs — async-first architecture
- Identity blocks (agent_persona, user_profile) are read-only for agents — user action required
- Operational blocks (task_state, tool_results_cache, active_goals) accept any valid JSON in v1 — strict schemas deferred to v1.1
- Classification hybrid control: automatic default (internal), agents can elevate to confidential, users can set any level
- Working Memory message boundaries defined by logical turns (aggregate until tool call or user input)
- Token budget: 4 chars/token approximation with 10% safety margin reduction
- Conversation logs unlimited retention by default — user-managed via memory:prune
- memory:prune command defaults to dry-run — requires --force or --execute flag
- Backfill retry limit: 5 consolidation cycles (10 hours) before marking permanently unrecoverable
- Dual Horizon queues: memory-working (high priority, fire-and-forget) and memory-formation (normal priority, retryable)
- Cost estimation via hardcoded pricing tables updated with releases — no dynamic fetching
- Provider abstraction fallback: Direct HTTP clients (Guzzle) if laravel/ai 0.x unsuitable
- Migrations use sequential timestamps with 1-minute gaps ordered by dependency chain
- All API keys encrypted at rest via Laravel encrypted cast; masked in output; never logged
- Per-user scoping only in v1; schema forward-compatible for scope_type/scope_id
- Additive migrations only (PostgreSQL already in use)
- Layer 4 (Delegation Memory), FastAPI, LangGraph/CrewAI integrations deferred to v2
- WebSocket diagnostics via Laravel Reverb — self-hosted, no external dependencies


## Acceptance Criteria

- MemoryServiceProvider registers all services gated on config('memory.enabled'); system fully inert when disabled
- CoreMemoryManager provides CRUD for 5 block keys with version increment, classification, and audit logging
- Identity blocks (agent_persona, user_profile) reject write attempts from agent API calls; only user-initiated requests succeed
- Operational blocks accept any valid JSON without schema validation in v1
- WorkingMemoryBuffer aggregates output by logical turn boundaries (tool call or user input)
- WorkingMemoryBuffer retains last 15 logical turns per run with configurable MEMORY_WORKING_MAX_MESSAGES
- No-API eviction uses oldest-first truncation; API eviction uses LLM summarization with truncation fallback
- MemoryFormationJob extracts technical entities: Person, Organization, Location, Date, Concept, File, Function, Class, API, Error, Dependency
- Formation job retries 5× with exponential backoff [10s, 30s, 60s, 120s, 300s]; exhausted retries create MemoryFormationFailure record
- Consolidation backfill retries failed formations for 5 cycles (10 hours) before marking unrecoverable
- HybridRetriever executes parallel source queries with RRF fusion (k=60) and returns partial results when sources unavailable
- Missing pgvector skips semantic search and returns BM25 + graph results (or BM25-only if Neo4j also unavailable)
- MemoryContextBuilder generates wrapper file with 5% context budget, 10% margin reduction, clamped to [1000..8000] tokens
- ForgettingService implements tiered pruning; memory:prune defaults to dry-run requiring --force to execute
- ConsolidationService runs every 2 hours with checkpoint-resumable processing
- All database tables enforce user_id scoping; classification filtering applied on all queries
- Settings API provides encrypted storage, masked key output (last 4 chars), connection testing, and capabilities endpoint
- MemoryCapabilityResolver correctly detects operating mode (no-api/api/degraded) based on provider keys, pgvector availability, and Neo4j connectivity
- Provider usage tracked in memory_provider_usage with cost estimates from hardcoded pricing tables; keys never appear in logs
- memory:stats command reports per-layer diagnostics including storage sizes, entity counts, and provider usage
- All 12 API endpoints use Sanctum auth with dedicated throttle buckets (reads: 120/min, writes: 30/min)
- Rate limiter implements proactive token-bucket per provider+model with reactive 429/Retry-After handling and jitter
- 8 migrations run cleanly with sequential timestamps; pgvector migration is idempotent and non-fatal
- Horizon supervisor-memory-working and supervisor-memory-formation configured with env-adjustable worker counts (default 3 each)
- Neo4j deployed as Docker Compose service with health check endpoint
- Vue.js Memory Settings page exposes full Expert-level configuration: provider keys, model selection, rate limits, token budgets, forgetting thresholds
- Real-time diagnostics delivered via Laravel Reverb WebSocket for formation jobs, consolidation progress, and provider usage
- User deletion cascades to all memory tables via ON DELETE CASCADE; memory:purge-user command cleans Neo4j graph data
- Concurrent formation jobs for same user handle embedding dedup via content_hash uniqueness with INSERT ON CONFLICT DO NOTHING
- Neo4j entity creation uses MERGE for idempotent concurrent handling

