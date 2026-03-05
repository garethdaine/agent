# Memory in Chat vs Agent Ops Memory Pipeline

## Overview

Agent Ops has two distinct memory systems that serve different purposes. Understanding when each is used prevents confusion and ensures the right data feeds the right context.

## System Comparison

| Aspect | Chat Context (Short-term) | Memory Pipeline (Long-term) |
|--------|--------------------------|----------------------------|
| **Scope** | Single chat session | Cross-session, cross-connector |
| **Storage** | `chat_messages` table | `memories` table with vector embeddings |
| **Lifetime** | Until session archived/pruned | Indefinite (with decay scoring) |
| **Format** | Raw message text | Structured memories with importance scores |
| **Retrieval** | Sequential (last N messages) | Semantic search (vector similarity) |
| **Write trigger** | Every inbound/outbound message | Memory Formation Pipeline (async job) |
| **Purpose** | Immediate conversation continuity | Long-term knowledge, preferences, patterns |

## Chat Context (Short-term)

### How It Works

1. User sends a message via Discord/Slack/Telegram/WhatsApp
2. Message is stored in `chat_messages` with the `chat_session_id`
3. When building the LLM prompt, the system loads the last N messages from the session
4. These messages provide immediate conversational context

### Key Properties

- **Session-scoped**: Only messages from the current session are included
- **Order-dependent**: Messages are loaded chronologically
- **No semantic filtering**: All recent messages are included regardless of relevance
- **Compaction**: After a threshold, older messages are summarized to save context window space

### Configuration

```php
// config/messenger.php
'context' => [
    'max_messages' => env('MESSENGER_CONTEXT_MAX_MESSAGES', 20),
    'compaction_threshold' => env('MESSENGER_COMPACTION_THRESHOLD', 200),
],
```

## Memory Pipeline (Long-term)

### How It Works

1. **Formation**: After significant interactions, `MemoryFormationJob` runs asynchronously
2. **Extraction**: The formation pipeline extracts key facts, preferences, and patterns
3. **Embedding**: Each memory is converted to a vector embedding (via OpenAI embeddings API)
4. **Storage**: Memories are stored in the `memories` table with pgvector
5. **Retrieval**: At prompt-build time, `MemoryContextBuilder` performs semantic search against the current conversation to find relevant memories
6. **Consolidation**: Periodic `MemoryConsolidationJob` merges related memories
7. **Decay**: `ForgettingService` applies time-based importance decay

### Key Properties

- **Cross-session**: Memories persist across sessions and connectors
- **Semantic**: Retrieved by meaning similarity, not chronological order
- **Curated**: Only significant information becomes a memory (not every message)
- **Scored**: Each memory has an importance score that decays over time

### Pipeline Flow

```
Message → MemoryFormationPipeline → MemoryFormationJob
    ↓
Extract facts/preferences/patterns
    ↓
Generate vector embedding
    ↓
Store in memories table (pgvector)
    ↓
[Periodic] MemoryConsolidationJob → merge related memories
    ↓
[Periodic] ForgettingService → decay old, low-importance memories
```

### Configuration

```php
// config/memory.php
'formation' => [
    'min_message_count' => 3,
    'importance_threshold' => 0.3,
],
'consolidation' => [
    'similarity_threshold' => 0.85,
    'max_batch_size' => 50,
],
'forgetting' => [
    'decay_rate' => 0.01,
    'min_importance' => 0.1,
],
```

## How They Work Together

When building context for an LLM call:

```
1. Load last N chat messages (short-term context)
2. Semantic search memories table using current message (long-term context)
3. Combine into system prompt:
   - System instructions
   - Relevant long-term memories (top-K by similarity)
   - Recent chat messages (chronological)
   - Current user message
```

## Diagnostic Commands

```bash
# Check memory pipeline health
php artisan memory:stats

# Force consolidation
php artisan memory:consolidate

# View memory graph
php artisan memory:graph-snapshot

# Prune old memories
php artisan memory:prune
```

## UI Surfaces

- **Settings → Memory**: View memory stats and trigger operations
- **Messenger → Chat History**: Browse raw chat messages per session
- **Tools → Diagnostics**: Health check includes memory pipeline status
