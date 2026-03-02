# Implementation Plan

Derived from discovery session 14.

# Agent Memory v3 Implementation Plan

## Phase 1: Foundation Infrastructure

### 1.1 Configuration & Dependencies
- Create `config/memory.php` with all settings: feature flags (`memory.enabled`, `memory.api_enabled`), provider configuration, rate limits, pricing tables (OpenAI/Anthropic per-model costs), entity types (Person, Organization, Location, Date, Concept, File, Function, Class, API, Error, Dependency), forgetting thresholds, token budget parameters, queue names
- Update `composer.json`: add `laravel/ai` (branch 0.x), `laudis/neo4j-php-client`, `laravel/reverb`
- Run `composer update` and resolve any dependency conflicts
- Add Redis `memory` connection (DB 2) to `config/database.php` after line 194 (isolated from Horizon's DB 0)
- Add `supervisor-memory-working` and `supervisor-memory-formation` queues to `config/horizon.php` waits array and defaults/environments sections with env-configurable worker counts (default 3 each)
- Add memory context path `storage/app/memory/context/` to `allowed_task_markdown_bases` in `config/agent.php`
- Configure Laravel Reverb in `config/broadcasting.php` (self-hosted WebSocket)

### 1.2 Docker Compose Neo4j Service
- Create `docker-compose.yml` at project root (or extend existing) with Neo4j 5.x Community service
- Configure Neo4j volumes, ports (7474 HTTP, 7687 Bolt), environment variables (NEO4J_AUTH)
- Add health check configuration for Neo4j container
- Document production deployment (containerized Neo4j alongside existing stack)

### 1.3 Database Migrations (8 migrations, sequential 1-minute timestamps)
- Migration 1: `memory_settings` — id, user_id (nullable FK → users ON DELETE CASCADE), key (varchar 100), value (text encrypted), timestamps; unique(user_id, key)
- Migration 2: `memory_core_blocks` — id, user_id (FK NOT NULL → users ON DELETE CASCADE), job_id (nullable FK → agent_jobs ON DELETE CASCADE), block_type (varchar 20), block_key (varchar 100), content_text (text nullable), content_json (jsonb nullable), classification (varchar 20 default 'internal'), version (integer default 1), timestamps; unique(user_id, block_key, job_id); index(user_id, classification)
- Migration 3: `memory_embeddings` — id, user_id (FK NOT NULL → users ON DELETE CASCADE), source_type (varchar 30), source_id (varchar 100), content (text), content_hash (varchar 64), embedding (vector(1536) nullable), metadata_json (jsonb nullable), classification (varchar 20 default 'internal'), importance_score (double precision default 0.5), access_count (integer default 0), last_accessed_at (timestamptz nullable), created_at; indexes: HNSW on embedding (conditional on pgvector), GIN on tsvector(content), index(user_id, classification), index(content_hash); unique constraint on content_hash with INSERT ON CONFLICT DO NOTHING pattern
- Migration 4: `memory_conversation_logs` — id, user_id (FK → users ON DELETE CASCADE), run_id (FK → agent_job_runs ON DELETE CASCADE), job_id (FK → agent_jobs ON DELETE CASCADE), role (varchar 20), content (text), sequence (integer), event_type (varchar 20), classification (varchar 20 default 'internal'), created_at; indexes: (user_id, run_id, sequence), (user_id, classification), GIN on tsvector(content)
- Migration 5: `memory_consolidation_log` — id, user_id (nullable FK → users ON DELETE SET NULL), consolidation_type (varchar 30), source_count (integer), result_summary (text nullable), checkpoint_json (jsonb nullable), created_at
- Migration 6: `memory_formation_failures` — id, run_id (FK → agent_job_runs ON DELETE CASCADE), user_id (FK → users ON DELETE CASCADE), failure_type (varchar 30), error_message (text), attempts (integer default 0), backfilled_at (timestamptz nullable), payload_json (jsonb), created_at; partial index WHERE backfilled_at IS NULL
- Migration 7: `memory_provider_usage` — id, user_id (FK → users ON DELETE CASCADE), run_id (nullable FK → agent_job_runs ON DELETE SET NULL), provider (varchar 30), model (varchar 100), operation (varchar 30), input_tokens (integer), output_tokens (integer nullable), cost_estimate_usd (double precision nullable), created_at
- Migration 8: `enable_pgvector_extension` — conditional CREATE EXTENSION IF NOT EXISTS vector; wrapped in try/catch, non-fatal, logs warning on failure

### 1.4 Eloquent Models (7 models)
- `MemorySetting` — fillable [user_id, key, value], encrypted cast on value, `getForUser()`/`setForUser()` pattern (mirroring InterrogationSetting), scoped queries
- `MemoryCoreBlock` — fillable [user_id, job_id, block_type, block_key, content_text, content_json, classification, version], JSON cast on content_json, belongs to User/AgentJob, version auto-increment on save via model observer
- `MemoryEmbedding` — fillable [user_id, source_type, source_id, content, content_hash, embedding, metadata_json, classification, importance_score, access_count, last_accessed_at], JSON cast on metadata_json, belongs to User
- `MemoryConversationLog` — fillable [user_id, run_id, job_id, role, content, sequence, event_type, classification], belongs to User/AgentJobRun/AgentJob
- `MemoryConsolidationLog` — fillable [user_id, consolidation_type, source_count, result_summary, checkpoint_json], JSON cast on checkpoint_json, belongs to User (nullable)
- `MemoryFormationFailure` — fillable [run_id, user_id, failure_type, error_message, attempts, backfilled_at, payload_json], JSON cast on payload_json, belongs to User/AgentJobRun, scope for unrecovered (where backfilled_at is null)
- `MemoryProviderUsage` — fillable [user_id, run_id, provider, model, operation, input_tokens, output_tokens, cost_estimate_usd], belongs to User/AgentJobRun

### 1.5 Service Provider
- Create `MemoryServiceProvider` in `app/Providers/`
- Register all services as singletons, gated on `config('memory.enabled')` — when disabled, services return no-ops or throw configuration exceptions
- Bind contracts (`EmbeddingProvider`, `ExtractionProvider`) to resolved implementations via `MemoryAdapterFactory`
- Register scheduled commands (`memory:consolidate`, `memory:prune`)
- Register Horizon queue configuration
- Add to `bootstrap/providers.php`

---

## Phase 2: Core Memory (Layer 1)

### 2.1 CoreMemoryManager Service
- Create `app/Support/Memory/CoreMemoryManager.php`
- Implement CRUD operations for 5 block keys: `agent_persona`, `user_profile`, `task_state`, `tool_results_cache`, `active_goals`
- Identity blocks (`agent_persona`, `user_profile`): accept freeform text via `content_text`, reject writes from agent API context (check request origin)
- Operational blocks (`task_state`, `tool_results_cache`, `active_goals`): validate JSON via `content_json`, accept any valid JSON in v1 (permissive schema)
- Auto-increment `version` column on every update via model observer
- Enforce classification levels: `public`, `internal` (default), `confidential`
- Agent classification control: can elevate to `confidential`, cannot downgrade below `internal`
- User classification control: can set any level via UI
- Audit logging via `AuditLogger::recordSystemAction()` with target_type `memory_core_block`
- Scoping: identity blocks scoped per `user_id`; operational blocks additionally scoped per `job_id`

### 2.2 Memory Settings Service
- Create `app/Support/Memory/MemorySettingsService.php`
- Implement `MemorySetting` CRUD following `InterrogationSetting` pattern
- Setting keys: `memory_enabled`, `api_features_enabled`, `extraction_provider`, `extraction_model`, `summarization_provider`, `summarization_model`, `embeddings_provider`, `embeddings_model`, `provider_key_openai` (encrypted), `provider_key_anthropic` (encrypted), `embedding_dimensions`
- Key masking: display only last 4 characters (e.g., `sk-...a1b2`)
- Keys NEVER logged via AuditLogger (log setting changes without values)
- Connection testing methods for each provider
- Capabilities detection via `MemoryCapabilityResolver`

### 2.3 MemoryCapabilityResolver Service
- Create `app/Support/Memory/MemoryCapabilityResolver.php`
- Auto-detect operating mode at runtime by inspecting:
  1. Configured provider keys in `memory_settings`
  2. pgvector extension availability (`SELECT 1 FROM pg_extension WHERE extname='vector'`)
  3. Neo4j connectivity (Bolt ping)
- Return mode: `no-api` (no keys), `api` (full), `degraded` (Anthropic-only: text extraction yes, embeddings no)
- Cache capability detection for request lifecycle (avoid repeated DB/Neo4j queries)

---

## Phase 3: Provider Abstraction

### 3.1 Contracts
- Create `app/Support/Memory/Contracts/EmbeddingProvider.php` — interface with `embed(string $content): array`, `embedBatch(array $contents): array`, `getDimensions(): int`
- Create `app/Support/Memory/Contracts/ExtractionProvider.php` — interface with `extractEntities(string $content): array`, `scoreImportance(string $content): float`, `summarize(string $content, int $maxTokens): string`

### 3.2 Adapter Factory
- Create `app/Support/Memory/MemoryAdapterFactory.php`
- Resolve adapter implementations based on Memory Settings
- Primary: attempt `laravel/ai` SDK (branch 0.x) for OpenAI and Anthropic
- Fallback: direct HTTP clients (Guzzle) if `laravel/ai` unsuitable
- Three model tiers configuration: Extraction (gpt-4o-mini), Summarization (gpt-4.1-nano), Embeddings (text-embedding-3-small)

### 3.3 OpenAI Adapter
- Create `app/Support/Memory/Adapters/OpenAIAdapter.php`
- Implement both `EmbeddingProvider` and `ExtractionProvider` contracts
- Use `laravel/ai` SDK or Guzzle fallback
- Fixed 1536-dimension embeddings for v1
- Entity extraction prompt templates for NER + technical entities
- Importance scoring prompt
- Summarization for Working Memory eviction

### 3.4 Anthropic Adapter
- Create `app/Support/Memory/Adapters/AnthropicAdapter.php`
- Implement `ExtractionProvider` only (Anthropic has no embeddings API)
- Text extraction enabled (entity extraction, summarization)
- Semantic embeddings throw `CapabilityNotSupportedException`

### 3.5 Guzzle HTTP Adapter (Fallback)
- Create `app/Support/Memory/Adapters/GuzzleHttpAdapter.php`
- Direct HTTP client implementation if `laravel/ai` 0.x is unsuitable
- Wrap behind same contracts for seamless swap

### 3.6 Rate Limiter
- Create `app/Support/Memory/RateLimiter/ProviderRateLimiter.php`
- Proactive Redis token-bucket per provider+model key
- Reactive 429/Retry-After handling with jitter
- Rate limit config per provider from `config/memory.php`
- Define rate limiters in `AppServiceProvider::boot()` following existing pattern

### 3.7 Provider Usage Tracker
- Create `app/Support/Memory/ProviderUsageTracker.php`
- Track all provider API calls in `memory_provider_usage` table
- Cost estimation via hardcoded pricing tables in `config/memory.php`
- Input/output token counts per operation
- Keys NEVER in logs

---

## Phase 4: Working Memory (Layer 2)

### 4.1 WorkingMemoryBuffer Service
- Create `app/Support/Memory/WorkingMemoryBuffer.php`
- Redis sorted sets keyed by `memory:run:{run_id}:working`, scored by microsecond timestamp
- Use Redis `memory` connection (DB 2)
- Message boundaries: aggregate all output until tool call or user input boundary (logical turn)
- Last 15 logical turns retained (configurable via `MEMORY_WORKING_MAX_MESSAGES`)
- Methods: `append()`, `retrieve(int $count)`, `getAll()`, `clear()`

### 4.2 Eviction Strategy
- No-API Mode: oldest-first truncation via `ZREMRANGEBYRANK`
- API Mode: LLM summarization of oldest entries using summarization model (gpt-4.1-nano)
- Fallback: if summarization fails (provider error, timeout), fall back to truncation
- Configurable summarization threshold

### 4.3 TTL Management
- TTL: 2 hours post terminal status via Redis `EXPIREAT` (configurable via `MEMORY_WORKING_TTL`)
- Set expiry when run reaches terminal status (completed, failed, cancelled)
- Integration with `ExecuteAgentRunJob::finalizeTerminal()`

### 4.4 MemoryWorkingBufferJob
- Create `app/Jobs/Memory/MemoryWorkingBufferJob.php`
- Fire-and-forget job dispatched from `RunEventWriter::appendOutput()`
- Queue: `memory-working` (high priority)
- Retry: 0 (silent failure — zero backpressure on 250ms poll loop)
- Latency target: <1ms (Redis ZADD only)
- Guard: `config('memory.enabled')`, wrapped in isolated try/catch

---

## Phase 5: Long-term Memory (Layer 3)

### 5.1 MemoryFormationPipeline Service
- Create `app/Support/Memory/MemoryFormationPipeline.php`
- Orchestrate post-run memory formation:
  1. Retrieve Working Memory buffer for completed run
  2. Persist conversation log entries to `memory_conversation_logs`
  3. Extract entities via `ExtractionProvider::extractEntities()` (API mode only)
  4. Score importance via `ExtractionProvider::scoreImportance()` (API mode only)
  5. Generate embeddings via `EmbeddingProvider::embed()` (API mode only, requires pgvector + embeddings provider)
  6. Persist embeddings to `memory_embeddings` with content_hash dedup (`INSERT ON CONFLICT DO NOTHING`)
  7. Store entities/relationships in Neo4j via `Neo4jGraphStore` (API mode only, requires Neo4j)
- Handle partial failures: persist what succeeded, record failure for backfill

### 5.2 Neo4jGraphStore Service
- Create `app/Support/Memory/Neo4jGraphStore.php`
- Use `laudis/neo4j-php-client` for Neo4j 5.x Bolt protocol
- Entity creation via `MERGE` for idempotent concurrent handling
- Entity types: Person, Organization, Location, Date, Concept, File, Function, Class, API, Error, Dependency
- Bi-temporal metadata stored from day one (valid_from, valid_to, transaction_time)
- Classification filtering on all Cypher queries
- Health check method for Neo4j connectivity
- Graph traversal queries for retrieval

### 5.3 HybridRetriever Service
- Create `app/Support/Memory/HybridRetriever.php`
- Execute parallel source queries:
  1. Semantic search via pgvector (when available)
  2. Keyword search (BM25) via PostgreSQL GIN tsvector indexes (always available)
  3. Knowledge graph traversal via Neo4j (when available)
- Reciprocal Rank Fusion (RRF) with k=60
- Configurable per-source weights: `semantic_weight`, `keyword_weight`, `graph_weight` (default 1.0 each)
- Return partial results when any source unavailable (never throws)
- Classification filtering enforced on all queries
- Update `access_count` and `last_accessed_at` on retrieved embeddings

### 5.4 MemoryFormationJob
- Create `app/Jobs/Memory/MemoryFormationJob.php`
- Queue: `memory-formation` (normal priority, retryable)
- Retry: 5× with exponential backoff [10s, 30s, 60s, 120s, 300s]
- On exhausted retries: create `MemoryFormationFailure` record with serialized payload for backfill
- Triggered from `ExecuteAgentRunJob::finalizeTerminal()` on terminal status
- Guard: `config('memory.enabled')`, wrapped in try/catch — never blocks finalization

### 5.5 Integration with ExecuteAgentRunJob
- Modify `app/Jobs/ExecuteAgentRunJob.php` (lines 43-91, 298, 379):
  1. Context injection hook between run loading and `renderTokens()` (handle method)
  2. `MEMORY_API_BASE_URL` environment injection in `mergedEnvironment()` after line 298
  3. `MemoryFormationJob` dispatch in `finalizeTerminal()` after `applyPathFailurePolicy()` (line 379)
- All modifications guarded by `config('memory.enabled')` + try/catch

### 5.6 Integration with RunEventWriter
- Modify `app/Support/Agent/RunEventWriter.php` after line 52:
- Dispatch `MemoryWorkingBufferJob` after `persistRunStats()` in `appendOutput()`
- Fire-and-forget, <1ms target, isolated try/catch
- Guard: `config('memory.enabled')`

---

## Phase 6: Consolidation, Forgetting & Commands

### 6.1 ConsolidationService
- Create `app/Support/Memory/ConsolidationService.php`
- Operations:
  1. Working → Core merge: summarize Working Memory insights into Core blocks (API mode only)
  2. Vector deduplication: identify near-duplicate embeddings via cosine similarity, merge metadata
  3. Failure backfill: retry failed formations from `MemoryFormationFailure` with serialized payloads
  4. Checkpoint-resumable processing following `AgentMaintenanceCheckpoint` pattern
- Backfill retry limit: 5 consolidation cycles (10 hours total) before marking permanently unrecoverable

### 6.2 ForgettingService
- Create `app/Support/Memory/ForgettingService.php`
- Tiered forgetting:
  1. Working Memory: Redis TTL (2 hours post terminal status)
  2. Vector embeddings: composite decay scoring (`importance × recency_decay × access_frequency_bonus`), prune below 0.1 threshold
  3. Graph entities: LLM-scored importance (API) or age-based (no-API), soft-delete below 0.2 after 90-day retention
  4. Conversation logs: unlimited retention by default (user-managed)
- Dry-run support: preview what would be deleted without executing

### 6.3 MemoryConsolidationJob
- Create `app/Jobs/Memory/MemoryConsolidationJob.php`
- Queue: `memory-formation`
- Scheduled every 2 hours
- Invokes `ConsolidationService`
- Event-driven trigger option for immediate consolidation

### 6.4 MemoryPruneJob
- Create `app/Jobs/Memory/MemoryPruneJob.php`
- Queue: `memory-formation`
- Scheduled daily at 03:30 with `--force` flag
- Invokes `ForgettingService`

### 6.5 Artisan Commands
- `memory:consolidate` — Create `app/Console/Commands/Memory/MemoryConsolidateCommand.php`; execute consolidation immediately; no flags
- `memory:prune` — Create `app/Console/Commands/Memory/MemoryPruneCommand.php`; defaults to dry-run (preview only); requires `--force` or `--execute` flag to actually delete; outputs preview of items to be pruned
- `memory:stats` — Create `app/Console/Commands/Memory/MemoryStatsCommand.php`; display per-layer diagnostics: storage sizes, entity counts, provider usage, cost totals
- `memory:graph-snapshot` — Create `app/Console/Commands/Memory/MemoryGraphSnapshotCommand.php`; point-in-time inspection using bi-temporal metadata
- `memory:purge-user {userId}` — Create `app/Console/Commands/Memory/MemoryPurgeUserCommand.php`; cascade delete all user memory data + Neo4j graph cleanup; GDPR compliance

### 6.6 Schedule Registration
- Update `routes/console.php`:
  - `memory:consolidate` every 2 hours (`0 */2 * * *`)
  - `memory:prune --force` daily at 03:30

---

## Phase 7: Context Injection & HTTP API

### 7.1 MemoryContextBuilder Service
- Create `app/Support/Memory/MemoryContextBuilder.php`
- Generate wrapper markdown file prepending memory context to task instructions:
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
- Token budget calculation:
  1. Base: 5% of runner's context window (from runner config)
  2. Approximation: 4 chars/token
  3. Safety margin: 10% reduction after calculation
  4. Hard floor: 1,000 tokens
  5. Hard ceiling: 8,000 tokens
  6. Clamp to [1000..8000] range
- If retrieved context exceeds budget: truncate (v1) or summarize (v1.1)
- File storage follows `TaskMarkdownStorage` pattern: date subdirs, UUID filenames, under `memory/context/` prefix
- Override `$run->job->task_markdown_path` transiently in `ExecuteAgentRunJob::handle()` (not persisted to DB)
- Does NOT modify `CommandTemplateRenderer`

### 7.2 Environment Injection
- In `ExecuteAgentRunJob::mergedEnvironment()` after line 298:
- Inject `MEMORY_API_BASE_URL` with value `config('app.url') . '/agent/api/v1/memory'`
- Programmatic injection bypasses `EnvPolicy.forbidden_env_key_pattern`
- Enables agent process to call memory API endpoints during execution

### 7.3 HTTP API Controllers (12 endpoints)
- Create `app/Http/Controllers/Api/V1/Memory/` directory
- All endpoints under `/agent/api/v1/memory/` prefix
- Sanctum-guarded, dedicated throttle buckets:
  - `memory-reads`: 120 requests/minute
  - `memory-writes`: 30 requests/minute

**MemorySettingsController:**
- `GET /memory/settings` — Read all settings (keys masked, last 4 chars visible)
- `PUT /memory/settings` — Update settings batch
- `POST /memory/settings/test-connection` — Test provider connectivity (OpenAI, Anthropic, Neo4j)
- `GET /memory/settings/capabilities` — Operating mode + available features

**MemoryCoreBlockController:**
- `GET /memory/core-blocks` — List blocks for auth user
- `GET /memory/core-blocks/{key}` — Read single block
- `PUT /memory/core-blocks/{key}` — Create/update block (rejects agent writes for identity blocks)
- `DELETE /memory/core-blocks/{key}` — Soft-delete block

**MemoryRetrievalController:**
- `POST /memory/retrieve` — Hybrid retrieval query (accepts query string, returns RRF-fused results)

**MemoryWorkingController:**
- `POST /memory/working/append` — Append to working memory (for agent process use)
- `GET /memory/working/{runId}` — Read working memory for run

**MemoryDiagnosticsController:**
- `GET /memory/stats` — Diagnostics + provider usage (mirrors `memory:stats` command)

### 7.4 Form Requests
- Create `app/Http/Requests/Memory/UpdateSettingsRequest.php`
- Create `app/Http/Requests/Memory/UpdateCoreBlockRequest.php`
- Create `app/Http/Requests/Memory/RetrieveMemoryRequest.php`
- Validation rules for each endpoint

### 7.5 API Routes
- Update `routes/api.php` line 16 area:
- Add `memory/` sub-group inside existing `agent/api/v1` prefix
- Apply Sanctum auth middleware
- Apply dedicated throttle middleware (`throttle:memory-reads` or `throttle:memory-writes`)

### 7.6 Rate Limiter Registration
- Update `app/Providers/AppServiceProvider.php` boot():
- Define `memory-reads` rate limiter (120/min per user)
- Define `memory-writes` rate limiter (30/min per user)

---

## Phase 8: Vue.js Settings Page & Real-time Diagnostics

### 8.1 Memory Settings Page (Expert Level)
- Create `resources/js/Pages/Tools/Memory/Settings.vue`
- Follow existing Discovery/Settings.vue pattern (Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Input components)
- Sections:
  1. **Provider Configuration**: API key inputs (OpenAI, Anthropic) with masked display, save/test buttons
  2. **Model Selection**: Dropdowns for extraction model, summarization model, embeddings model
  3. **Connection Testing**: Test buttons for each provider + Neo4j, status indicators
  4. **Rate Limit Configuration**: Inputs for per-provider rate limits
  5. **Token Budget Settings**: Context window percentage, floor/ceiling inputs
  6. **Forgetting Thresholds**: Decay parameters, retention period inputs, importance thresholds
  7. **Real-time Diagnostics Panel**: WebSocket-connected live view

### 8.2 WebSocket Integration
- Configure Laravel Reverb channels in `routes/channels.php`:
  - `memory.user.{userId}` — user-specific memory events
  - `memory.formation.{runId}` — formation job progress
  - `memory.consolidation` — consolidation progress (admin-level)
- Create broadcast events:
  - `MemoryFormationProgress` — emitted during formation pipeline
  - `MemoryConsolidationProgress` — emitted during consolidation
  - `MemoryProviderUsageUpdated` — emitted after provider API calls
- Real-time diagnostics in Settings page:
  - Formation job progress indicators
  - Consolidation progress bar
  - Live provider usage metrics
  - Cost tracking updates

### 8.3 Tools Index Update
- Update `resources/js/Pages/Tools/Index.vue`:
- Add Memory tool card to `tools` array:
  ```javascript
  {
      route: 'tools.memory.settings',
      category: 'Agent Memory',
      title: 'Memory configuration',
      description: 'Configure agent memory providers, retrieval settings, and view real-time diagnostics.',
      icon: Brain, // from lucide-vue-next
  }
  ```

### 8.4 Web Routes
- Update `routes/web.php`:
- Add route `GET /tools/memory/settings` rendering `Tools/Memory/Settings` page
- Named route `tools.memory.settings`

### 8.5 Navigation Discoverability
- Memory Settings accessible from Tools index page via card link
- Back button navigation to Tools index (matching Discovery/Settings.vue pattern)
- Page title in AppLayout header

---

## Phase 9: Integration Testing & Validation

### 9.1 Feature Tests
- Test `MemoryServiceProvider` registration gating (system inert when `memory.enabled=false`)
- Test `CoreMemoryManager` CRUD with version increment, classification, audit logging
- Test identity block write rejection from agent API context
- Test operational block JSON acceptance (any valid JSON)
- Test `WorkingMemoryBuffer` logical turn aggregation and retention limits
- Test eviction strategies (truncation and summarization fallback)
- Test `MemoryFormationJob` retry behavior and failure recording
- Test consolidation backfill retry limit (5 cycles before unrecoverable)
- Test `HybridRetriever` partial results when sources unavailable
- Test pgvector degradation to BM25-only
- Test Neo4j degradation to BM25 + conversation logs
- Test `MemoryContextBuilder` token budget calculation and clamping
- Test `ForgettingService` dry-run vs execute modes
- Test classification filtering on all queries
- Test settings API key masking
- Test `MemoryCapabilityResolver` mode detection
- Test provider usage tracking (keys not in logs)
- Test API endpoint authentication and throttling
- Test rate limiter proactive + reactive behavior
- Test migration idempotency (pgvector non-fatal)
- Test user deletion cascade + `memory:purge-user` Neo4j cleanup
- Test concurrent formation job dedup (content_hash uniqueness)
- Test Neo4j `MERGE` idempotency

### 9.2 Browser Tests (Playwright)
- Test Memory Settings page loads from Tools index
- Test navigation to/from Memory Settings
- Test provider key input, masking, and save
- Test connection test buttons and status indicators
- Test model selection dropdowns
- Test real-time diagnostics panel WebSocket connection
- Test form validation errors display
- Test settings persistence across page reloads

### 9.3 Performance Validation
- Verify `MemoryWorkingBufferJob` completes in <1ms (Redis ZADD only)
- Verify formation jobs do not block agent run finalization
- Verify Horizon supervisor scaling under load
- Verify Redis memory connection isolation (DB 2)
- Verify Neo4j health check response time

### 9.4 Documentation
- Update README with memory feature overview
- Document Docker Compose Neo4j setup for local development
- Document production Neo4j deployment (containerized)
- Document environment variables for memory configuration
- Document API endpoint usage for agent process integration

## Sections

- Phase 1: Foundation Infrastructure
- Phase 2: Core Memory (Layer 1)
- Phase 3: Provider Abstraction
- Phase 4: Working Memory (Layer 2)
- Phase 5: Long-term Memory (Layer 3)
- Phase 6: Consolidation, Forgetting & Commands
- Phase 7: Context Injection & HTTP API
- Phase 8: Vue.js Settings Page & Real-time Diagnostics
- Phase 9: Integration Testing & Validation


## Risks

- laravel/ai SDK 0.x branch may be immature or incompatible — fallback to direct Guzzle HTTP clients required if package is unsuitable, isolated by contract-first design
- Neo4j introduces new infrastructure dependency — containerized deployment mitigates operational complexity; feature-flag gating allows disable without code changes
- pgvector extension may not be installed on PostgreSQL server — graceful degradation to BM25-only retrieval implemented; non-fatal migration with warning logging
- RunEventWriter hot path modification risks poll loop degradation — fire-and-forget pattern with <1ms target, zero retries, isolated try/catch prevents backpressure
- ExecuteAgentRunJob modification risks run execution failure — all insertions guarded by config flag + try/catch; instant rollback via MEMORY_ENABLED=false
- Token budget approximation (4 chars/token) may over/under inject context — conservative ratio + 10% safety margin + hard ceiling mitigates; monitoring via stats command
- Provider API costs may accumulate unexpectedly — usage tracking with cost estimates, medium-tier models for extraction, cheaper models for summarization
- Async formation may lose data on repeated failures — MemoryFormationFailure table captures serialized payload for backfill; 5-cycle consolidation retry before marking unrecoverable
- APP_KEY rotation would invalidate encrypted memory settings — documented procedure; re-encryption command planned for v1.1
- WebSocket (Laravel Reverb) may add complexity to deployment — self-hosted, no external dependencies; graceful fallback to polling if WebSocket unavailable


## Assumptions

- PostgreSQL 18 is active and accessible (confirmed)
- Redis is available and Horizon is using it (confirmed, default DB 0)
- Docker is available for Neo4j container deployment
- laravel/ai package branch 0.x is usable or direct HTTP fallback is acceptable
- Volume remains under 20 runs/day; 3 memory workers per queue sufficient
- APP_KEY is stable during deployment
- InterrogationSetting, AdapterFactory, and MaintenancePruneService patterns are canonical and should be followed
- Per-user scoping only in v1; schema forward-compatible for future scope_type/scope_id
- Layer 4 (Delegation Memory) is deferred to v2
- 4 chars/token approximation is acceptable for context budget calculation
- Horizon has capacity for 2 additional supervisors (memory-working, memory-formation)
- API routes follow /agent/api/v1/ prefix convention
- Vue.js settings pages follow Tools/Discovery/Settings.vue component pattern
- Laravel Reverb is suitable for self-hosted WebSocket without external dependencies

