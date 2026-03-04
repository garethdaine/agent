# Implementation Plan

Derived from discovery session 14.

# Agent Memory v3 — Implementation Plan

## Executive Summary

Implement a four-layer memory architecture (Layers 1–3) integrated into the existing PHP 8.3 / Laravel 12 Agent Ops. The system provides agents with persistent context across runs through editable identity blocks (Core Memory), short-lived conversational state (Working Memory), and durable knowledge with hybrid semantic + keyword + graph retrieval (Long-term Memory). Layer 4 (Delegation Memory) defers to v2.

Two operating modes ensure the feature is useful without any third-party API keys:
- **No-API Mode**: Core Memory + Working Memory + BM25 keyword retrieval + conversation logs
- **API Mode**: Full pipeline with LLM entity extraction, semantic embeddings (pgvector), Neo4j knowledge graph, and hybrid RRF retrieval

---

## Phase 1: Foundation Infrastructure

### 1.1 Configuration Setup

**File: `config/memory.php`**

Create comprehensive configuration file containing:
- Master toggle `memory.enabled` (default: false)
- API features toggle `memory.api_enabled` (default: false)
- Provider configuration structure for OpenAI and Anthropic
- Three model tiers with defaults: extraction (gpt-4o-mini), summarization (gpt-4.1-nano), embeddings (text-embedding-3-small)
- Rate limiting configuration per provider with token-bucket parameters
- Hardcoded pricing tables for cost estimation
- Working memory settings: max_messages (15), ttl_seconds (7200)
- Context injection settings: budget_percent (5), floor_tokens (1000), ceiling_tokens (8000), margin_percent (10)
- Forgetting thresholds: embedding_decay_threshold (0.1), graph_importance_threshold (0.2), graph_retention_days (90)
- Knowledge graph entity types: Person, Organization, Location, Date, Concept, File, Function, Class, API, Error, Dependency
- RRF retrieval settings: k (60), semantic_weight (1.0), keyword_weight (1.0), graph_weight (1.0)

**File: `config/database.php`**

Modify Redis configuration to add memory connection:
```php
'memory' => [
    'url' => env('REDIS_URL'),
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'username' => env('REDIS_USERNAME'),
    'password' => env('REDIS_PASSWORD'),
    'port' => env('REDIS_PORT', '6379'),
    'database' => env('REDIS_MEMORY_DB', '2'),
],
```

**File: `config/horizon.php`**

Add two new supervisors after existing supervisor-delegation:
```php
'supervisor-memory-working' => [
    'connection' => 'redis',
    'queue' => ['memory-working'],
    'balance' => 'auto',
    'maxProcesses' => max(1, min(8, (int) env('HORIZON_MEMORY_WORKING_MAX_PROCESSES', 3))),
    'tries' => 0,
    'timeout' => 5,
],
'supervisor-memory-formation' => [
    'connection' => 'redis',
    'queue' => ['memory-formation'],
    'balance' => 'auto',
    'maxProcesses' => max(1, min(8, (int) env('HORIZON_MEMORY_FORMATION_MAX_PROCESSES', 3))),
    'tries' => 5,
    'backoff' => [10, 30, 60, 120, 300],
    'timeout' => 300,
],
```

Add wait thresholds:
```php
'redis:memory-working' => 5,
'redis:memory-formation' => 60,
```

**File: `config/agent.php`**

Add memory context path to `allowed_task_markdown_bases`:
```php
storage_path('app/memory/context'),
```

**File: `docker-compose.yml`**

Create new file with Neo4j 5.x Community service:
```yaml
services:
  neo4j:
    image: neo4j:5-community
    container_name: agent-neo4j
    ports:
      - "7474:7474"
      - "7687:7687"
    environment:
      NEO4J_AUTH: neo4j/${NEO4J_PASSWORD:-password}
      NEO4J_PLUGINS: '["apoc"]'
    volumes:
      - neo4j_data:/data
      - neo4j_logs:/logs
    healthcheck:
      test: ["CMD", "wget", "-qO-", "http://localhost:7474"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  neo4j_data:
  neo4j_logs:
```

**File: `composer.json`**

Add new dependencies:
```json
"laravel/ai": "0.x-dev",
"laudis/neo4j-php-client": "^3.0"
```

### 1.2 Database Migrations

Create 8 migrations with sequential timestamps (1-minute gaps):

**Migration 1: `create_memory_settings_table`**
```php
Schema::create('memory_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('key', 100);
    $table->text('value'); // encrypted cast in model
    $table->timestamps();
    $table->unique(['user_id', 'key']);
});
```

**Migration 2: `create_memory_core_blocks_table`**
```php
Schema::create('memory_core_blocks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('job_id')->nullable()->constrained('agent_jobs')->cascadeOnDelete();
    $table->string('block_type', 20); // 'identity' or 'operational'
    $table->string('block_key', 100);
    $table->text('content_text')->nullable();
    $table->jsonb('content_json')->nullable();
    $table->string('classification', 20)->default('internal');
    $table->integer('version')->default(1);
    $table->timestamps();
    $table->unique(['user_id', 'block_key', 'job_id']);
    $table->index(['user_id', 'classification']);
});
```

**Migration 3: `create_memory_embeddings_table`**
```php
Schema::create('memory_embeddings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('source_type', 30);
    $table->string('source_id', 100);
    $table->text('content');
    $table->string('content_hash', 64);
    // vector column added conditionally below
    $table->jsonb('metadata_json')->nullable();
    $table->string('classification', 20)->default('internal');
    $table->float('importance_score')->default(0.5);
    $table->integer('access_count')->default(0);
    $table->timestampTz('last_accessed_at')->nullable();
    $table->timestampTz('created_at');
    $table->index(['user_id', 'classification']);
    $table->index('content_hash');
});

// Conditional vector column and HNSW index
try {
    DB::statement('ALTER TABLE memory_embeddings ADD COLUMN embedding vector(1536)');
    DB::statement('CREATE INDEX memory_embeddings_embedding_idx ON memory_embeddings USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 128)');
} catch (\Throwable $e) {
    // pgvector not available, graceful degradation
}

// BM25 tsvector index (always available)
DB::statement("CREATE INDEX memory_embeddings_content_fts ON memory_embeddings USING GIN (to_tsvector('english', content))");
```

**Migration 4: `create_memory_conversation_logs_table`**
```php
Schema::create('memory_conversation_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('run_id')->constrained('agent_job_runs')->cascadeOnDelete();
    $table->foreignId('job_id')->constrained('agent_jobs')->cascadeOnDelete();
    $table->string('role', 20);
    $table->text('content');
    $table->integer('sequence');
    $table->string('event_type', 20);
    $table->string('classification', 20)->default('internal');
    $table->timestampTz('created_at');
    $table->index(['user_id', 'run_id', 'sequence']);
    $table->index(['user_id', 'classification']);
});

DB::statement("CREATE INDEX memory_conversation_logs_content_fts ON memory_conversation_logs USING GIN (to_tsvector('english', content))");
```

**Migration 5: `create_memory_consolidation_log_table`**
```php
Schema::create('memory_consolidation_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('consolidation_type', 30);
    $table->integer('source_count');
    $table->text('result_summary')->nullable();
    $table->jsonb('checkpoint_json')->nullable();
    $table->timestampTz('created_at');
});
```

**Migration 6: `create_memory_formation_failures_table`**
```php
Schema::create('memory_formation_failures', function (Blueprint $table) {
    $table->id();
    $table->foreignId('run_id')->constrained('agent_job_runs')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('failure_type', 30);
    $table->text('error_message');
    $table->integer('attempts')->default(1);
    $table->timestampTz('backfilled_at')->nullable();
    $table->jsonb('payload_json');
    $table->timestampTz('created_at');
    $table->index(['backfilled_at']);
});

DB::statement('CREATE INDEX memory_formation_failures_pending ON memory_formation_failures (id) WHERE backfilled_at IS NULL');
```

**Migration 7: `create_memory_provider_usage_table`**
```php
Schema::create('memory_provider_usage', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('run_id')->nullable()->constrained('agent_job_runs')->cascadeOnDelete();
    $table->string('provider', 30);
    $table->string('model', 100);
    $table->string('operation', 30);
    $table->integer('input_tokens');
    $table->integer('output_tokens')->nullable();
    $table->float('cost_estimate_usd')->nullable();
    $table->timestampTz('created_at');
});
```

**Migration 8: `enable_pgvector_extension`**

Create idempotent, non-fatal migration:
```php
public function up(): void
{
    try {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    } catch (\Throwable $e) {
        // Extension not available, system degrades gracefully to BM25
        Log::info('pgvector extension not available, semantic search disabled');
    }
}
```

### 1.3 Models

Create 7 Eloquent models in `app/Models/`:

**MemorySetting** — encrypted value cast, `getForUser()`/`setForUser()` pattern following InterrogationSetting
**MemoryCoreBlock** — version tracking, classification validation, content type enforcement
**MemoryEmbedding** — content_hash generation, access tracking
**MemoryConversationLog** — immutable records
**MemoryConsolidationLog** — checkpoint serialization
**MemoryFormationFailure** — payload serialization, backfill tracking
**MemoryProviderUsage** — cost calculation helper methods

### 1.4 Service Provider

**File: `app/Providers/MemoryServiceProvider.php`**

Register all memory services gated on `config('memory.enabled')`:
- Singleton bindings for all services
- Contract bindings for provider interfaces
- Configuration publication
- Merge configuration

**File: `bootstrap/providers.php`**

Add `App\Providers\MemoryServiceProvider::class` to providers array.

---

## Phase 2: Core Memory (Layer 1)

### 2.1 Core Memory Manager Service

**File: `app/Support/Memory/CoreMemoryManager.php`**

Implement CRUD operations for 5 block keys:
- `agent_persona` (identity, freeform text, user-only write)
- `user_profile` (identity, freeform text, user-only write)
- `task_state` (operational, permissive JSON, agent-editable)
- `tool_results_cache` (operational, permissive JSON, agent-editable)
- `active_goals` (operational, permissive JSON, agent-editable)

Key methods:
- `get(int $userId, string $blockKey, ?int $jobId = null): ?MemoryCoreBlock`
- `set(int $userId, string $blockKey, mixed $content, ?int $jobId = null, array $options = []): MemoryCoreBlock`
- `delete(int $userId, string $blockKey, ?int $jobId = null): bool`
- `listBlocks(int $userId, ?int $jobId = null): Collection`

Features:
- Automatic version increment on update using optimistic locking
- Classification validation (public, internal, confidential)
- Identity vs operational block type enforcement
- Agent write permission checking for identity blocks
- Audit logging via AuditLogger for all mutations
- User_id scoping on all queries

### 2.2 Memory Settings Service

**File: `app/Support/Memory/MemorySettingsService.php`**

Encrypted settings management following InterrogationSetting pattern:
- Provider API keys (encrypted at rest, masked in output)
- Model selection per tier
- Rate limit configuration
- Feature toggles

Key methods:
- `get(int $userId, string $key, mixed $default = null): mixed`
- `set(int $userId, string $key, mixed $value): void`
- `getAll(int $userId): array` (keys masked)
- `testConnection(int $userId, string $provider): ConnectionTestResult`

Setting keys:
- `memory_enabled`, `api_features_enabled`
- `extraction_provider`, `extraction_model`
- `summarization_provider`, `summarization_model`
- `embeddings_provider`, `embeddings_model`
- `provider_key_openai`, `provider_key_anthropic` (encrypted)
- `embedding_dimensions`

### 2.3 API Controllers

**File: `app/Http/Controllers/Api/V1/Memory/MemorySettingsController.php`**

Endpoints:
- `GET /memory/settings` — Read all settings (keys masked, show last 4 chars)
- `PUT /memory/settings` — Batch update settings
- `POST /memory/settings/test-connection` — Test provider connectivity
- `GET /memory/settings/capabilities` — Return operating mode and available features

**File: `app/Http/Controllers/Api/V1/Memory/MemoryCoreBlockController.php`**

Endpoints:
- `GET /memory/core-blocks` — List blocks for authenticated user
- `GET /memory/core-blocks/{key}` — Read single block
- `PUT /memory/core-blocks/{key}` — Create/update block (enforce agent vs user permissions)
- `DELETE /memory/core-blocks/{key}` — Soft-delete block

### 2.4 Form Requests

**File: `app/Http/Requests/Memory/UpdateMemorySettingsRequest.php`**
**File: `app/Http/Requests/Memory/UpdateCoreBlockRequest.php`**

Validation rules for all memory API inputs.

### 2.5 Rate Limiters

**File: `app/Providers/AppServiceProvider.php`**

Add memory-specific rate limiters in `boot()`:
```php
RateLimiter::for('memory-reads', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id));
RateLimiter::for('memory-writes', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id));
```

### 2.6 Routes

**File: `routes/api.php`**

Add memory route group inside existing `/agent/api/v1` prefix after delegation routes:
```php
Route::prefix('memory')->middleware(['memory'])->group(function (): void {
    // Settings
    Route::get('/settings', [MemorySettingsController::class, 'index'])->middleware('throttle:memory-reads');
    Route::put('/settings', [MemorySettingsController::class, 'update'])->middleware('throttle:memory-writes');
    Route::post('/settings/test-connection', [MemorySettingsController::class, 'testConnection'])->middleware('throttle:memory-writes');
    Route::get('/settings/capabilities', [MemorySettingsController::class, 'capabilities'])->middleware('throttle:memory-reads');
    
    // Core Blocks
    Route::get('/core-blocks', [MemoryCoreBlockController::class, 'index'])->middleware('throttle:memory-reads');
    Route::get('/core-blocks/{key}', [MemoryCoreBlockController::class, 'show'])->middleware('throttle:memory-reads');
    Route::put('/core-blocks/{key}', [MemoryCoreBlockController::class, 'update'])->middleware('throttle:memory-writes');
    Route::delete('/core-blocks/{key}', [MemoryCoreBlockController::class, 'destroy'])->middleware('throttle:memory-writes');
    
    // ... additional routes added in later phases
});
```

### 2.7 Middleware

**File: `app/Http/Middleware/Memory/MemoryEnabled.php`**

Gate middleware that returns 503 when `config('memory.enabled')` is false.

---

## Phase 3: Provider Abstraction

### 3.1 Contracts

**File: `app/Support/Memory/Contracts/EmbeddingProvider.php`**
```php
interface EmbeddingProvider
{
    public function embed(string $text): ?array;
    public function embedBatch(array $texts): array;
    public function getDimensions(): int;
    public function getProviderName(): string;
    public function supportsEmbeddings(): bool;
}
```

**File: `app/Support/Memory/Contracts/ExtractionProvider.php`**
```php
interface ExtractionProvider
{
    public function extractEntities(string $text): array;
    public function scoreImportance(string $text, array $entities): float;
    public function summarize(string $text, int $maxTokens): string;
    public function getProviderName(): string;
    public function supportsTextGeneration(): bool;
}
```

### 3.2 Adapters

**File: `app/Support/Memory/Adapters/OpenAIAdapter.php`**

Implements both `EmbeddingProvider` and `ExtractionProvider`:
- Embedding via text-embedding-3-small (1536d)
- Entity extraction via gpt-4o-mini with structured output
- Importance scoring via gpt-4o-mini
- Summarization via gpt-4.1-nano

Primary implementation path: `laravel/ai` SDK (branch 0.x)
Fallback implementation: Direct Guzzle HTTP client

**File: `app/Support/Memory/Adapters/AnthropicAdapter.php`**

Implements `ExtractionProvider` only (no embeddings support):
- Entity extraction via Claude
- Importance scoring
- Summarization

**File: `app/Support/Memory/Adapters/GuzzleHttpAdapter.php`**

Direct HTTP fallback when `laravel/ai` is unavailable or unsuitable:
- Guzzle-based HTTP clients for OpenAI and Anthropic APIs
- Implements same contracts as SDK adapters
- Used if `laravel/ai` package evaluation fails

### 3.3 Adapter Factory

**File: `app/Support/Memory/MemoryAdapterFactory.php`**

Factory resolving adapters based on Memory Settings:
```php
public function makeEmbeddingProvider(int $userId): ?EmbeddingProvider
public function makeExtractionProvider(int $userId): ?ExtractionProvider
public function makeSummarizationProvider(int $userId): ?ExtractionProvider
```

Features:
- Auto-detection of configured providers
- Failover chain for text extraction (OpenAI ↔ Anthropic)
- Embeddings failover only across embedding-capable providers
- Caches resolved adapters per request

### 3.4 Rate Limiter

**File: `app/Support/Memory/RateLimiter/ProviderRateLimiter.php`**

Proactive token-bucket rate limiting per provider+model:
- Redis-backed token buckets
- Configurable rates per provider in `config/memory.php`
- Reactive 429/Retry-After handling with jitter
- Rate limit state tracking for dashboard display

### 3.5 Provider Usage Tracker

**File: `app/Support/Memory/ProviderUsageTracker.php`**

Track all provider API calls:
- Input/output token counts
- Cost estimation from hardcoded pricing tables
- Per-run and aggregate usage tracking
- Never logs API key values

### 3.6 Capability Resolver

**File: `app/Support/Memory/MemoryCapabilityResolver.php`**

Runtime mode detection:
```php
public function getOperatingMode(int $userId): string // 'no-api', 'api', 'degraded'
public function getCapabilities(int $userId): array
```

Inspects:
1. Configured provider keys in memory_settings
2. pgvector extension availability via `SELECT 1 FROM pg_extension WHERE extname='vector'`
3. Neo4j connectivity via bolt connection test

---

## Phase 4: Working Memory (Layer 2)

### 4.1 Working Memory Buffer Service

**File: `app/Support/Memory/WorkingMemoryBuffer.php`**

Redis-backed sorted set implementation:
- Key pattern: `memory:run:{run_id}:working`
- Score: microsecond timestamp
- Last 15 logical turns retained

Key methods:
```php
public function append(int $runId, string $role, string $content, array $metadata = []): void
public function getRecent(int $runId, int $limit = 15): array
public function clear(int $runId): void
public function setTTL(int $runId, int $seconds = 7200): void
```

Message boundary detection:
- Aggregate output until tool call or user input boundary
- Parse structured events to detect turn boundaries

Eviction strategies:
- No-API Mode: `ZREMRANGEBYRANK` oldest-first truncation
- API Mode: LLM summarization of oldest entries with fallback to truncation

### 4.2 Working Memory Buffer Job

**File: `app/Jobs/Memory/MemoryWorkingBufferJob.php`**

Fire-and-forget job for Working Memory appends:
- Queue: `memory-working`
- Tries: 0 (silent failure)
- Timeout: 5 seconds
- <1ms Redis ZADD operation
- Isolated try/catch — never blocks agent execution

### 4.3 Summarization Service

**File: `app/Support/Memory/WorkingMemorySummarizer.php`**

Eviction summarization for API mode:
- Uses summarization model tier (gpt-4.1-nano default)
- Summarizes oldest N turns when buffer exceeds limit
- Fallback to truncation on provider failure
- Tracks summarization in provider usage

### 4.4 Integration Point: RunEventWriter

**File: `app/Support/Agent/RunEventWriter.php`**

Add Working Memory dispatch after `persistRunStats()` in `appendOutput()` method (after line 66):
```php
// Memory integration: buffer to Working Memory (fire-and-forget)
if (config('memory.enabled')) {
    try {
        MemoryWorkingBufferJob::dispatch(
            $this->run->id,
            $eventType,
            $rawPayload
        )->onQueue('memory-working');
    } catch (\Throwable $e) {
        // Silent failure - never block the 250ms poll loop
    }
}
```

### 4.5 API Endpoints

**File: `app/Http/Controllers/Api/V1/Memory/MemoryWorkingController.php`**

Endpoints:
- `POST /memory/working/append` — Append to working memory (for agent self-calls)
- `GET /memory/working/{runId}` — Read working memory for run

---

## Phase 5: Long-term Memory (Layer 3)

### 5.1 Memory Formation Pipeline

**File: `app/Support/Memory/MemoryFormationPipeline.php`**

Orchestrates Long-term Memory formation:
1. Retrieve Working Memory buffer for completed run
2. Persist conversation log entries to `memory_conversation_logs`
3. Extract entities via `ExtractionProvider::extractEntities()` (API mode)
4. Score importance via `ExtractionProvider::scoreImportance()` (API mode)
5. Generate embeddings via `EmbeddingProvider::embed()` (API mode)
6. Persist embeddings to `memory_embeddings` with content_hash dedup
7. Store entities/relationships in Neo4j via `Neo4jGraphStore` (API mode)
8. Handle partial failures: persist what succeeded + record failure

Entity types extracted:
- Standard NER: Person, Organization, Location, Date, Concept
- Technical: File, Function, Class, API, Error, Dependency

### 5.2 Memory Formation Job

**File: `app/Jobs/Memory/MemoryFormationJob.php`**

Long-term Memory formation job:
- Queue: `memory-formation`
- Tries: 5 with exponential backoff [10s, 30s, 60s, 120s, 300s]
- Timeout: 300 seconds
- On exhausted retries: create `MemoryFormationFailure` record with serialized payload

### 5.3 Integration Point: ExecuteAgentRunJob

**File: `app/Jobs/ExecuteAgentRunJob.php`**

Add Memory Formation dispatch in `finalizeTerminal()` after `applyPathFailurePolicy()` (around line 558):
```php
// Memory integration: dispatch formation job (async, non-blocking)
if (config('memory.enabled')) {
    try {
        MemoryFormationJob::dispatch($run->id)->onQueue('memory-formation');
    } catch (\Throwable $e) {
        // Log but never block finalization
        Log::warning('Failed to dispatch memory formation job', [
            'run_id' => $run->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

### 5.4 Neo4j Graph Store

**File: `app/Support/Memory/Neo4jGraphStore.php`**

Neo4j 5.x Community integration via `laudis/neo4j-php-client`:
- Entity creation with `MERGE` for idempotent concurrent handling
- Relationship creation between entities
- Bi-temporal metadata (valid_from, valid_to, recorded_at)
- Classification filtering in all queries
- Graph traversal for related entities

Node structure:
```cypher
(:Entity {
    id: uuid,
    user_id: int,
    type: string,
    name: string,
    classification: string,
    importance_score: float,
    access_count: int,
    created_at: datetime,
    updated_at: datetime,
    valid_from: datetime,
    valid_to: datetime
})
```

Key methods:
```php
public function storeEntities(int $userId, array $entities): void
public function storeRelationships(int $userId, array $relationships): void
public function queryRelated(int $userId, string $entityId, int $depth = 2): array
public function healthCheck(): bool
```

### 5.5 Hybrid Retriever

**File: `app/Support/Memory/HybridRetriever.php`**

Three-source retrieval with Reciprocal Rank Fusion:

```php
public function retrieve(int $userId, string $query, array $options = []): array
```

Sources:
1. **Semantic search** (pgvector) — cosine similarity on embeddings
2. **Keyword search** (PostgreSQL BM25) — tsvector full-text search
3. **Graph traversal** (Neo4j) — related entity discovery

RRF fusion with k=60:
```php
$fusedScore = 0;
foreach ($sources as $source => $rank) {
    $weight = $options["{$source}_weight"] ?? 1.0;
    $fusedScore += $weight * (1 / ($k + $rank));
}
```

Features:
- Parallel source queries via async
- Partial results when any source unavailable (never throws)
- Classification filtering enforced on all queries
- Graceful degradation: missing pgvector → BM25+graph; missing Neo4j → BM25+semantic

### 5.6 API Endpoints

**File: `app/Http/Controllers/Api/V1/Memory/MemoryRetrievalController.php`**

Endpoint:
- `POST /memory/retrieve` — Hybrid retrieval query

Request body:
```json
{
    "query": "string",
    "limit": 10,
    "classification": ["public", "internal"],
    "semantic_weight": 1.0,
    "keyword_weight": 1.0,
    "graph_weight": 1.0
}
```

---

## Phase 6: Consolidation & Forgetting

### 6.1 Consolidation Service

**File: `app/Support/Memory/ConsolidationService.php`**

Scheduled consolidation operations:
1. **Working → Core merge**: Summarize Working Memory insights into Core blocks (API mode only)
2. **Vector deduplication**: Identify near-duplicate embeddings via cosine similarity, merge metadata
3. **Failure backfill**: Retry failed formations from `MemoryFormationFailure` with serialized payloads
4. **Checkpoint**: Resumable processing following `AgentMaintenanceCheckpoint` pattern

Backfill retry limit: 5 consolidation cycles (10 hours total) before marking permanently unrecoverable.

### 6.2 Forgetting Service

**File: `app/Support/Memory/ForgettingService.php`**

Tiered pruning with configurable thresholds:

| Layer | Strategy | Threshold |
|-------|----------|-----------|
| Working Memory | Redis TTL | 2 hours post terminal status |
| Vector embeddings | Composite decay: `importance × recency_decay × access_frequency_bonus` | Prune below 0.1 |
| Graph entities | LLM-scored importance (API) or age-based (no-API) | Soft-delete below 0.2 after 90-day retention |
| Conversation logs | Unlimited retention by default | User-managed via command |

Key method:
```php
public function prune(int $userId, bool $dryRun = true): PruneResult
```

### 6.3 Artisan Commands

**File: `app/Console/Commands/Memory/MemoryConsolidateCommand.php`**
```bash
php artisan memory:consolidate [--user=] [--type=]
```

**File: `app/Console/Commands/Memory/MemoryPruneCommand.php`**
```bash
php artisan memory:prune [--user=] [--force|--execute]
```
Default: dry-run (preview only). Requires `--force` or `--execute` to actually delete.

**File: `app/Console/Commands/Memory/MemoryStatsCommand.php`**
```bash
php artisan memory:stats [--user=]
```
Per-layer diagnostics: storage sizes, entity counts, provider usage, cost tracking.

**File: `app/Console/Commands/Memory/MemoryGraphSnapshotCommand.php`**
```bash
php artisan memory:graph-snapshot {userId} [--output=]
```
Point-in-time graph inspection using bi-temporal metadata.

**File: `app/Console/Commands/Memory/MemoryPurgeUserCommand.php`**
```bash
php artisan memory:purge-user {userId}
```
GDPR compliance: cascade delete all user memory data including Neo4j cleanup.

### 6.4 Scheduled Commands

**File: `routes/console.php`**

Add after existing schedules:
```php
Schedule::command('memory:consolidate')
    ->cron('0 */2 * * *') // Every 2 hours
    ->withoutOverlapping()
    ->when(fn () => config('memory.enabled'));

Schedule::command('memory:prune --force')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->when(fn () => config('memory.enabled'));
```

### 6.5 Jobs

**File: `app/Jobs/Memory/MemoryConsolidationJob.php`**

Async consolidation job for event-driven triggers.

**File: `app/Jobs/Memory/MemoryPruneJob.php`**

Async pruning job for scheduled execution.

---

## Phase 7: Context Injection & Vue.js Settings UI

### 7.1 Memory Context Builder

**File: `app/Support/Memory/MemoryContextBuilder.php`**

Generates wrapper markdown file prepending memory context to task instructions:

```markdown
## Agent Identity
{core_memory.agent_persona}

## User Context
{core_memory.user_profile}

## Relevant Memories
{hybrid_retrieval_results — ranked by RRF, truncated to budget}

---
{original_task_content}
```

Token budget calculation:
1. Base: 5% of runner's context window (from runner config)
2. Approximation: 4 chars/token
3. Safety margin: 10% reduction after calculation
4. Clamp to [1000..8000] range

Key method:
```php
public function buildContext(AgentJobRun $run): string
```

File storage:
- Path pattern: `storage/app/memory/context/{date}/{uuid}.md`
- Follows `TaskMarkdownStorage` pattern
- Added to `allowed_task_markdown_bases` in Phase 1

### 7.2 Integration Point: ExecuteAgentRunJob Context Injection

**File: `app/Jobs/ExecuteAgentRunJob.php`**

Add context injection in `handle()` between run loading and `renderTokens()` (around line 54-91):
```php
// Memory context injection
if (config('memory.enabled')) {
    try {
        $contextBuilder = app(MemoryContextBuilder::class);
        $contextPath = $contextBuilder->buildContext($run);
        if ($contextPath !== null) {
            // Transiently override task path (not persisted to DB)
            $run->job->task_markdown_path = $contextPath;
        }
    } catch (\Throwable $e) {
        // Log but never block execution
        Log::warning('Memory context injection failed', [
            'run_id' => $run->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

### 7.3 Environment Injection

**File: `app/Jobs/ExecuteAgentRunJob.php`**

Add in `mergedEnvironment()` method (around line 458):
```php
// Memory API URL injection (programmatic, bypasses EnvPolicy)
if (config('memory.enabled')) {
    $env['MEMORY_API_BASE_URL'] = config('app.url') . '/agent/api/v1/memory';
}
```

### 7.4 Vue.js Memory Settings Page

**File: `resources/js/Pages/Tools/Memory/Settings.vue`**

Expert-level settings page following Discovery/Settings.vue pattern:

**Sections:**

1. **Provider Configuration**
   - API key inputs for OpenAI and Anthropic (password fields, show last 4 chars)
   - Model selection dropdowns for extraction, summarization, embeddings tiers
   - Connection test buttons with status indicators

2. **Operating Mode Display**
   - Current mode indicator (No-API / API / Degraded)
   - Feature availability checklist
   - pgvector status
   - Neo4j connectivity status

3. **Rate Limiting**
   - Per-provider rate limit configuration
   - Current usage display
   - Token bucket status

4. **Token Budget**
   - Context budget percentage slider
   - Floor/ceiling token inputs
   - Margin percentage input

5. **Forgetting Thresholds**
   - Embedding decay threshold slider
   - Graph importance threshold slider
   - Retention period inputs

6. **Real-time Diagnostics** (via Laravel Reverb WebSocket)
   - Formation job progress indicators
   - Consolidation progress
   - Provider usage metrics (live updates)
   - Cost tracking display

**File: `resources/js/Pages/Tools/Memory/Index.vue`**

Memory dashboard showing:
- Per-layer storage statistics
- Recent formation jobs status
- Provider usage summary
- Quick actions (consolidate, prune dry-run)

### 7.5 Tools Index Update

**File: `resources/js/Pages/Tools/Index.vue`**

Add Memory tool card to tools array:
```javascript
{
    route: 'tools.memory.settings',
    category: 'Agent Memory',
    title: 'Memory settings & diagnostics',
    description: 'Configure memory providers, view usage, and manage memory retention.',
    icon: Brain,
},
```

### 7.6 Web Routes

**File: `routes/web.php`**

Add Memory routes after existing tools routes:
```php
Route::get('/tools/memory', function () {
    return Inertia::render('Tools/Memory/Index');
})->name('tools.memory.index');

Route::get('/tools/memory/settings', function () {
    return Inertia::render('Tools/Memory/Settings');
})->name('tools.memory.settings');
```

### 7.7 WebSocket Channels

**File: `routes/channels.php`**

Add memory broadcast channels:
```php
Broadcast::channel('memory.diagnostics.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('memory.formation.{runId}', function ($user, $runId) {
    return AgentJobRun::where('id', $runId)
        ->whereHas('job', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
```

### 7.8 Broadcasting Events

**File: `app/Events/Memory/MemoryFormationProgress.php`**
**File: `app/Events/Memory/MemoryConsolidationProgress.php`**
**File: `app/Events/Memory/MemoryProviderUsageUpdated.php`**

Real-time events broadcast via Laravel Reverb.

### 7.9 Diagnostics Controller

**File: `app/Http/Controllers/Api/V1/Memory/MemoryDiagnosticsController.php`**

Endpoint:
- `GET /memory/stats` — Per-layer diagnostics + provider usage

---

## Phase 8: Integration Testing & Validation

### 8.1 Feature Tests

**File: `tests/Feature/Memory/CoreMemoryTest.php`**
- CRUD operations for all 5 block keys
- Version increment verification
- Classification enforcement
- Agent vs user write permissions for identity blocks

**File: `tests/Feature/Memory/WorkingMemoryTest.php`**
- Redis sorted set operations
- Message boundary detection
- Eviction strategies (truncation and summarization)
- TTL behavior

**File: `tests/Feature/Memory/LongTermMemoryTest.php`**
- Conversation log persistence
- Embedding generation and dedup
- Graph entity storage
- Hybrid retrieval fusion

**File: `tests/Feature/Memory/ConsolidationTest.php`**
- Checkpoint resumability
- Failure backfill
- Deduplication

**File: `tests/Feature/Memory/ContextInjectionTest.php`**
- Token budget calculation
- Wrapper file generation
- RunEventWriter integration
- ExecuteAgentRunJob integration

### 8.2 Unit Tests

**File: `tests/Unit/Memory/MemoryCapabilityResolverTest.php`**
- Mode detection (no-api, api, degraded)
- pgvector availability detection
- Neo4j connectivity detection

**File: `tests/Unit/Memory/HybridRetrieverTest.php`**
- RRF scoring
- Partial results handling
- Source weight configuration

**File: `tests/Unit/Memory/ProviderAdapterTest.php`**
- OpenAI adapter
- Anthropic adapter
- Fallback behavior

### 8.3 Integration Validation

- Run full agent execution with memory enabled
- Verify Working Memory populated during run
- Verify Long-term Memory formed on completion
- Verify context injection on subsequent runs
- Verify Vue.js settings page functionality
- Verify real-time diagnostics via WebSocket
- Verify graceful degradation without pgvector
- Verify graceful degradation without Neo4j
- Verify graceful degradation without provider keys

### 8.4 Performance Validation

- Working Memory append latency < 1ms
- Formation job completion within timeout
- Retrieval response time < 500ms
- No impact on 250ms agent poll loop

---

## Acceptance Criteria Verification

### System Behavior
- [ ] MemoryServiceProvider registers all services gated on `config('memory.enabled')`; system fully inert when disabled
- [ ] All 12 API endpoints use Sanctum auth with dedicated throttle buckets (reads: 120/min, writes: 30/min)
- [ ] 8 migrations run cleanly with sequential timestamps; pgvector migration is idempotent and non-fatal
- [ ] Horizon supervisor-memory-working and supervisor-memory-formation configured with env-adjustable worker counts

### Core Memory (Layer 1)
- [ ] CoreMemoryManager provides CRUD for 5 block keys with version increment, classification, and audit logging
- [ ] Identity blocks (agent_persona, user_profile) reject write attempts from agent API calls
- [ ] Operational blocks accept any valid JSON without schema validation in v1

### Working Memory (Layer 2)
- [ ] WorkingMemoryBuffer aggregates output by logical turn boundaries
- [ ] WorkingMemoryBuffer retains last 15 logical turns per run
- [ ] No-API eviction uses oldest-first truncation; API eviction uses LLM summarization with fallback

### Long-term Memory (Layer 3)
- [ ] MemoryFormationJob extracts technical entities: Person, Organization, Location, Date, Concept, File, Function, Class, API, Error, Dependency
- [ ] Formation job retries 5× with exponential backoff; exhausted retries create MemoryFormationFailure record
- [ ] HybridRetriever executes parallel source queries with RRF fusion (k=60) and returns partial results
- [ ] Missing pgvector skips semantic search and returns BM25 + graph results

### Context Injection
- [ ] MemoryContextBuilder generates wrapper file with 5% context budget, 10% margin reduction, clamped to [1000..8000] tokens

### Consolidation & Forgetting
- [ ] ConsolidationService runs every 2 hours with checkpoint-resumable processing
- [ ] Consolidation backfill retries failed formations for 5 cycles before marking unrecoverable
- [ ] ForgettingService implements tiered pruning; memory:prune defaults to dry-run requiring --force

### Provider Abstraction
- [ ] MemoryCapabilityResolver correctly detects operating mode (no-api/api/degraded)
- [ ] Rate limiter implements proactive token-bucket with reactive 429 handling and jitter
- [ ] Provider usage tracked in memory_provider_usage with cost estimates; keys never appear in logs

### Security & Compliance
- [ ] All database tables enforce user_id scoping; classification filtering applied on all queries
- [ ] Settings API provides encrypted storage, masked key output, connection testing, and capabilities endpoint
- [ ] User deletion cascades to all memory tables; memory:purge-user command cleans Neo4j graph data
- [ ] Concurrent formation jobs handle embedding dedup via content_hash uniqueness

### Infrastructure
- [ ] Neo4j deployed as Docker Compose service with health check endpoint
- [ ] Neo4j entity creation uses MERGE for idempotent concurrent handling

### User Interface
- [ ] Vue.js Memory Settings page exposes full Expert-level configuration
- [ ] Memory tool card visible in Tools index page with navigation to settings
- [ ] Real-time diagnostics delivered via Laravel Reverb WebSocket
- [ ] Connection test functionality for all providers and Neo4j

### Diagnostics
- [ ] memory:stats command reports per-layer diagnostics including storage sizes, entity counts, and provider usage

## Sections

- Phase 1: Foundation Infrastructure
- Phase 2: Core Memory (Layer 1)
- Phase 3: Provider Abstraction
- Phase 4: Working Memory (Layer 2)
- Phase 5: Long-term Memory (Layer 3)
- Phase 6: Consolidation & Forgetting
- Phase 7: Context Injection & Vue.js Settings UI
- Phase 8: Integration Testing & Validation


## Risks

- laravel/ai SDK (branch 0.x) may not be production-ready — mitigation: contracts-first design with GuzzleHttpAdapter fallback implementation ready before Phase 3 completion
- pgvector extension not installed on PostgreSQL 18 — mitigation: conditional migration with graceful degradation to BM25-only retrieval; system functional without semantic search
- Neo4j Community Edition lacks RBAC — mitigation: application-level access control enforcement on all queries with user_id scoping and classification filtering
- Neo4j operational complexity as new infrastructure dependency — mitigation: Docker Compose service definition with health checks; feature-flag gated allowing Neo4j-free operation
- RunEventWriter hot path sensitivity (250ms poll loop) — mitigation: fire-and-forget job dispatch with zero retries; isolated try/catch; <1ms Redis ZADD operation
- ExecuteAgentRunJob modification risk — mitigation: all integrations guarded by config flag + try/catch; instant rollback via MEMORY_ENABLED=false
- Token budget approximation accuracy (4 chars/token) — mitigation: conservative ratio with 10% safety margin + hard ceiling at 8000 tokens
- Provider cost accumulation — mitigation: medium-tier models for extraction; cheaper nano model for summarization; hardcoded pricing tables with usage tracking; real-time cost display in UI
- Async formation data loss on job failure — mitigation: MemoryFormationFailure table with serialized payload; 5-cycle consolidation backfill (10 hours); permanent failure marking
- APP_KEY rotation invalidates encrypted settings — mitigation: document procedure in deployment guide; re-encryption command planned for v1.1


## Assumptions

- PostgreSQL 18 is active and accessible (confirmed via existing migrations)
- Redis is available and accessible (confirmed via Horizon usage on DB 0)
- pgvector extension is NOT installed; system will degrade to BM25-only until installed
- Neo4j 5.x Community will be deployed alongside existing stack via Docker Compose
- laravel/ai branch 0.x is stable enough for use, or direct HTTP fallback will be implemented
- Run volume remains under 20 runs/day; 3 memory workers per queue is sufficient
- APP_KEY remains stable during deployment cycle
- Existing patterns (InterrogationSetting, AdapterFactory, MaintenancePruneService) are appropriate to follow
- Per-user scoping is sufficient for v1; multi-tenant scope_type/scope_id deferred
- Layer 4 (Delegation Memory) is fully deferred to v2
- 4 chars/token approximation is acceptable for context budget calculation
- Horizon has capacity for two additional supervisors (memory-working, memory-formation)
- API routes follow existing /agent/api/v1/ convention
- Laravel Reverb is already configured and operational (confirmed via config/reverb.php)
- Vue.js component patterns from Discovery/Settings.vue are appropriate to replicate

