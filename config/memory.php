<?php

declare(strict_types=1);

/**
 * Memory System Configuration
 *
 * Four-layer memory architecture integrated into the Agent Ops.
 * Provides agents with persistent context across runs through:
 * - Core Memory: Editable identity blocks
 * - Working Memory: Short-lived conversational state
 * - Long-term Memory: Durable knowledge with hybrid retrieval
 *
 * Two operating modes:
 * - No-API Mode: Core Memory + Working Memory + BM25 keyword retrieval
 * - API Mode: Full pipeline with LLM extraction, semantic embeddings, Neo4j graph
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Master Toggle
    |--------------------------------------------------------------------------
    |
    | When disabled, the entire memory system is inert. All memory services
    | are gated on this setting and will not register when false.
    |
    */
    'enabled' => (bool) env('MEMORY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | API Features Toggle
    |--------------------------------------------------------------------------
    |
    | When enabled (and provider keys are configured), enables LLM entity
    | extraction, semantic embeddings, and Neo4j knowledge graph population.
    | Requires 'enabled' to also be true.
    |
    */
    'api_enabled' => (bool) env('MEMORY_API_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | API provider settings for LLM operations. Keys are stored encrypted
    | in the database via MemorySetting model.
    |
    */
    'providers' => [
        'openai' => [
            'base_url' => env('MEMORY_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('MEMORY_OPENAI_TIMEOUT', 30),
        ],
        'anthropic' => [
            'base_url' => env('MEMORY_ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'timeout' => (int) env('MEMORY_ANTHROPIC_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Tiers
    |--------------------------------------------------------------------------
    |
    | Three model tiers with different cost/performance tradeoffs:
    | - extraction: Entity extraction and importance scoring
    | - summarization: Working memory eviction summarization
    | - embeddings: Semantic vector embeddings
    |
    */
    'models' => [
        'extraction' => [
            'default' => 'gpt-4o-mini',
            'provider' => 'openai',
            'fallback' => 'claude-3-haiku-20240307',
        ],
        'summarization' => [
            'default' => 'gpt-4.1-nano',
            'provider' => 'openai',
            'fallback' => 'claude-3-haiku-20240307',
        ],
        'embeddings' => [
            'default' => 'text-embedding-3-small',
            'provider' => 'openai',
            'dimensions' => 1536,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Known Models (Fallback)
    |--------------------------------------------------------------------------
    |
    | Curated model lists used when the provider API is unreachable or
    | doesn't expose a models endpoint (e.g. Anthropic). Grouped by
    | provider and capability.
    |
    */
    'known_models' => [
        'openai' => [
            'extraction' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4.1',
                'gpt-4.1-mini',
                'gpt-4.1-nano',
                'gpt-4-turbo',
                'o4-mini',
                'o3-mini',
            ],
            'summarization' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4.1',
                'gpt-4.1-mini',
                'gpt-4.1-nano',
                'gpt-4-turbo',
                'o4-mini',
                'o3-mini',
            ],
            'embeddings' => [
                'text-embedding-3-large',
                'text-embedding-3-small',
                'text-embedding-ada-002',
            ],
        ],
        'anthropic' => [
            'extraction' => [
                'claude-sonnet-4-20250514',
                'claude-haiku-4-5-20251001',
                'claude-3-5-sonnet-20241022',
                'claude-3-haiku-20240307',
            ],
            'summarization' => [
                'claude-sonnet-4-20250514',
                'claude-haiku-4-5-20251001',
                'claude-3-5-sonnet-20241022',
                'claude-3-haiku-20240307',
            ],
            'embeddings' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Token-bucket rate limiting configuration per provider.
    | Prevents hitting API rate limits with proactive throttling.
    |
    */
    'rate_limits' => [
        'openai' => [
            'requests_per_minute' => (int) env('MEMORY_OPENAI_RPM', 500),
            'tokens_per_minute' => (int) env('MEMORY_OPENAI_TPM', 200000),
            'requests_per_day' => (int) env('MEMORY_OPENAI_RPD', 10000),
        ],
        'anthropic' => [
            'requests_per_minute' => (int) env('MEMORY_ANTHROPIC_RPM', 50),
            'tokens_per_minute' => (int) env('MEMORY_ANTHROPIC_TPM', 100000),
            'requests_per_day' => (int) env('MEMORY_ANTHROPIC_RPD', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Tables
    |--------------------------------------------------------------------------
    |
    | Hardcoded pricing for cost estimation. All prices in USD per 1M tokens.
    | These should be updated when provider pricing changes.
    |
    */
    'pricing' => [
        'openai' => [
            'gpt-4o-mini' => [
                'input' => 0.15,
                'output' => 0.60,
            ],
            'gpt-4.1-nano' => [
                'input' => 0.10,
                'output' => 0.40,
            ],
            'text-embedding-3-small' => [
                'input' => 0.02,
                'output' => 0.0,
            ],
        ],
        'anthropic' => [
            'claude-3-haiku-20240307' => [
                'input' => 0.25,
                'output' => 1.25,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Working Memory Settings
    |--------------------------------------------------------------------------
    |
    | Redis-backed sorted set configuration for short-term conversational
    | state. Messages are aggregated by logical turn boundaries.
    |
    */
    'working_memory' => [
        'max_messages' => (int) env('MEMORY_WORKING_MAX_MESSAGES', 15),
        'ttl_seconds' => (int) env('MEMORY_WORKING_TTL_SECONDS', 7200),
        'key_prefix' => env('MEMORY_WORKING_KEY_PREFIX', 'memory:run:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Injection Settings
    |--------------------------------------------------------------------------
    |
    | Token budget calculation for injecting memory context into agent runs.
    | Budget is percentage of runner's context window with safety margins.
    |
    */
    'context_injection' => [
        'budget_percent' => (int) env('MEMORY_CONTEXT_BUDGET_PERCENT', 5),
        'floor_tokens' => (int) env('MEMORY_CONTEXT_FLOOR_TOKENS', 1000),
        'ceiling_tokens' => (int) env('MEMORY_CONTEXT_CEILING_TOKENS', 8000),
        'margin_percent' => (int) env('MEMORY_CONTEXT_MARGIN_PERCENT', 10),
        'chars_per_token' => 4,
        'default_context_window' => (int) env('MEMORY_DEFAULT_CONTEXT_WINDOW', 200000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Forgetting Thresholds
    |--------------------------------------------------------------------------
    |
    | Tiered pruning configuration for memory decay and cleanup.
    | Lower thresholds result in more aggressive forgetting.
    |
    */
    'forgetting' => [
        'embedding_decay_threshold' => (float) env('MEMORY_EMBEDDING_DECAY_THRESHOLD', 0.1),
        'graph_importance_threshold' => (float) env('MEMORY_GRAPH_IMPORTANCE_THRESHOLD', 0.2),
        'graph_retention_days' => (int) env('MEMORY_GRAPH_RETENTION_DAYS', 90),
        'working_memory_ttl_hours' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge Graph Entity Types
    |--------------------------------------------------------------------------
    |
    | Entity types extracted during memory formation.
    | Standard NER entities plus technical entities for code understanding.
    |
    */
    'entity_types' => [
        // Standard NER entities
        'Person',
        'Organization',
        'Location',
        'Date',
        'Concept',
        // Technical entities
        'File',
        'Function',
        'Class',
        'API',
        'Error',
        'Dependency',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval Settings
    |--------------------------------------------------------------------------
    |
    | Reciprocal Rank Fusion (RRF) configuration for hybrid retrieval.
    | Combines semantic, keyword, and graph search results.
    |
    */
    'retrieval' => [
        'k' => 60,
        'semantic_weight' => 1.0,
        'keyword_weight' => 1.0,
        'graph_weight' => 1.0,
        'default_limit' => 10,
        'max_limit' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Memory Block Types
    |--------------------------------------------------------------------------
    |
    | Defines the allowed block keys and their properties.
    | Identity blocks are user-only, operational blocks are agent-editable.
    |
    */
    'core_blocks' => [
        'agent_persona' => [
            'type' => 'identity',
            'storage' => 'text',
            'agent_editable' => false,
        ],
        'user_profile' => [
            'type' => 'identity',
            'storage' => 'text',
            'agent_editable' => false,
        ],
        'task_state' => [
            'type' => 'operational',
            'storage' => 'json',
            'agent_editable' => true,
        ],
        'tool_results_cache' => [
            'type' => 'operational',
            'storage' => 'json',
            'agent_editable' => true,
        ],
        'active_goals' => [
            'type' => 'operational',
            'storage' => 'json',
            'agent_editable' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Classification Levels
    |--------------------------------------------------------------------------
    |
    | Data classification for access control.
    | Agents can elevate to confidential but cannot downgrade.
    |
    */
    'classifications' => [
        'public',
        'internal',
        'confidential',
    ],

    'default_classification' => 'internal',

    /*
    |--------------------------------------------------------------------------
    | Neo4j Configuration
    |--------------------------------------------------------------------------
    |
    | Connection settings for Neo4j knowledge graph.
    | Uses bolt protocol for efficient graph operations.
    |
    */
    'neo4j' => [
        'host' => env('NEO4J_HOST', 'localhost'),
        'port' => (int) env('NEO4J_PORT', 7687),
        'username' => env('NEO4J_USERNAME', 'neo4j'),
        'password' => env('NEO4J_PASSWORD', 'password'),
        'database' => env('NEO4J_DATABASE', 'neo4j'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formation Pipeline
    |--------------------------------------------------------------------------
    |
    | Configuration for long-term memory formation from completed runs.
    |
    */
    'formation' => [
        'retry_attempts' => 5,
        'backoff_seconds' => [10, 30, 60, 120, 300],
        'timeout_seconds' => 300,
        'consolidation_backfill_cycles' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consolidation Schedule
    |--------------------------------------------------------------------------
    |
    | Configuration for scheduled memory consolidation operations.
    |
    */
    'consolidation' => [
        'interval_hours' => 2,
        'prune_time' => '03:30',
        'checkpoint_enabled' => true,
    ],
];
