<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Tests for memory system configuration infrastructure.
 *
 * Verifies:
 * - config/memory.php exists with correct defaults
 * - Redis memory connection is configured for DB 2
 * - Horizon supervisors include memory-working and memory-formation
 * - Memory context path is in allowed_task_markdown_bases
 */
class MemoryConfigurationTest extends TestCase
{
    /**
     * Test that memory.enabled defaults to false.
     */
    public function test_memory_enabled_defaults_to_false(): void
    {
        $this->assertFalse(config('memory.enabled'));
    }

    /**
     * Test that memory.api_enabled defaults to false.
     */
    public function test_memory_api_enabled_defaults_to_false(): void
    {
        $this->assertFalse(config('memory.api_enabled'));
    }

    /**
     * Test that config/memory.php contains required keys.
     */
    public function test_memory_config_contains_required_keys(): void
    {
        $config = config('memory');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('api_enabled', $config);
        $this->assertArrayHasKey('providers', $config);
        $this->assertArrayHasKey('models', $config);
        $this->assertArrayHasKey('rate_limits', $config);
        $this->assertArrayHasKey('pricing', $config);
        $this->assertArrayHasKey('working_memory', $config);
        $this->assertArrayHasKey('context_injection', $config);
        $this->assertArrayHasKey('forgetting', $config);
        $this->assertArrayHasKey('entity_types', $config);
        $this->assertArrayHasKey('retrieval', $config);
    }

    /**
     * Test that memory.models has three tiers.
     */
    public function test_memory_config_has_three_model_tiers(): void
    {
        $models = config('memory.models');

        $this->assertIsArray($models);
        $this->assertArrayHasKey('extraction', $models);
        $this->assertArrayHasKey('summarization', $models);
        $this->assertArrayHasKey('embeddings', $models);

        // Verify default models
        $this->assertEquals('gpt-4o-mini', $models['extraction']['default']);
        $this->assertEquals('gpt-4.1-nano', $models['summarization']['default']);
        $this->assertEquals('text-embedding-3-small', $models['embeddings']['default']);
    }

    /**
     * Test that working memory has correct defaults.
     */
    public function test_memory_config_working_memory_defaults(): void
    {
        $workingMemory = config('memory.working_memory');

        $this->assertIsArray($workingMemory);
        $this->assertEquals(15, $workingMemory['max_messages']);
        $this->assertEquals(7200, $workingMemory['ttl_seconds']);
    }

    /**
     * Test that context injection has correct defaults.
     */
    public function test_memory_config_context_injection_defaults(): void
    {
        $contextInjection = config('memory.context_injection');

        $this->assertIsArray($contextInjection);
        $this->assertEquals(5, $contextInjection['budget_percent']);
        $this->assertEquals(1000, $contextInjection['floor_tokens']);
        $this->assertEquals(8000, $contextInjection['ceiling_tokens']);
        $this->assertEquals(10, $contextInjection['margin_percent']);
    }

    /**
     * Test that forgetting thresholds are configured.
     */
    public function test_memory_config_forgetting_thresholds(): void
    {
        $forgetting = config('memory.forgetting');

        $this->assertIsArray($forgetting);
        $this->assertEquals(0.1, $forgetting['embedding_decay_threshold']);
        $this->assertEquals(0.2, $forgetting['graph_importance_threshold']);
        $this->assertEquals(90, $forgetting['graph_retention_days']);
    }

    /**
     * Test that entity types include standard NER and technical types.
     */
    public function test_memory_config_entity_types(): void
    {
        $entityTypes = config('memory.entity_types');

        $this->assertIsArray($entityTypes);

        // Standard NER entities
        $this->assertContains('Person', $entityTypes);
        $this->assertContains('Organization', $entityTypes);
        $this->assertContains('Location', $entityTypes);
        $this->assertContains('Date', $entityTypes);
        $this->assertContains('Concept', $entityTypes);

        // Technical entities
        $this->assertContains('File', $entityTypes);
        $this->assertContains('Function', $entityTypes);
        $this->assertContains('Class', $entityTypes);
        $this->assertContains('API', $entityTypes);
        $this->assertContains('Error', $entityTypes);
        $this->assertContains('Dependency', $entityTypes);
    }

    /**
     * Test that retrieval RRF settings are configured.
     */
    public function test_memory_config_retrieval_settings(): void
    {
        $retrieval = config('memory.retrieval');

        $this->assertIsArray($retrieval);
        $this->assertEquals(60, $retrieval['k']);
        $this->assertEquals(1.0, $retrieval['semantic_weight']);
        $this->assertEquals(1.0, $retrieval['keyword_weight']);
        $this->assertEquals(1.0, $retrieval['graph_weight']);
    }

    /**
     * Test that Redis memory connection is configured for DB 2.
     */
    public function test_redis_memory_connection_uses_database_2(): void
    {
        $memoryConfig = config('database.redis.memory');

        $this->assertIsArray($memoryConfig);
        $this->assertEquals('2', $memoryConfig['database']);
    }

    /**
     * Test that Redis memory connection has required keys.
     */
    public function test_redis_memory_connection_has_required_keys(): void
    {
        $memoryConfig = config('database.redis.memory');

        $this->assertIsArray($memoryConfig);
        $this->assertArrayHasKey('host', $memoryConfig);
        $this->assertArrayHasKey('port', $memoryConfig);
        $this->assertArrayHasKey('database', $memoryConfig);
    }

    /**
     * Test that Horizon includes supervisor-memory-working.
     */
    public function test_horizon_includes_memory_working_supervisor(): void
    {
        $defaults = config('horizon.defaults');

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('supervisor-memory-working', $defaults);

        $supervisor = $defaults['supervisor-memory-working'];
        $this->assertEquals(['memory-working'], $supervisor['queue']);
        $this->assertEquals(0, $supervisor['tries']);
        $this->assertEquals(5, $supervisor['timeout']);
    }

    /**
     * Test that Horizon includes supervisor-memory-formation.
     */
    public function test_horizon_includes_memory_formation_supervisor(): void
    {
        $defaults = config('horizon.defaults');

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('supervisor-memory-formation', $defaults);

        $supervisor = $defaults['supervisor-memory-formation'];
        $this->assertEquals(['memory-formation'], $supervisor['queue']);
        $this->assertEquals(5, $supervisor['tries']);
        $this->assertEquals([10, 30, 60, 120, 300], $supervisor['backoff']);
        $this->assertEquals(300, $supervisor['timeout']);
    }

    /**
     * Test that Horizon waits include memory queues.
     */
    public function test_horizon_waits_include_memory_queues(): void
    {
        $waits = config('horizon.waits');

        $this->assertIsArray($waits);
        $this->assertArrayHasKey('redis:memory-working', $waits);
        $this->assertArrayHasKey('redis:memory-formation', $waits);
        $this->assertEquals(5, $waits['redis:memory-working']);
        $this->assertEquals(60, $waits['redis:memory-formation']);
    }

    /**
     * Test that memory context path is in allowed_task_markdown_bases.
     */
    public function test_memory_context_path_in_allowed_task_markdown_bases(): void
    {
        $bases = config('agent.allowed_task_markdown_bases');

        $this->assertIsArray($bases);

        $memoryContextPath = storage_path('app/memory/context');
        $this->assertContains($memoryContextPath, $bases);
    }

    /**
     * Test that pricing tables are configured for cost estimation.
     */
    public function test_memory_config_pricing_tables(): void
    {
        $pricing = config('memory.pricing');

        $this->assertIsArray($pricing);
        $this->assertArrayHasKey('openai', $pricing);
        $this->assertArrayHasKey('anthropic', $pricing);

        // Verify OpenAI pricing structure
        $this->assertArrayHasKey('gpt-4o-mini', $pricing['openai']);
        $this->assertArrayHasKey('text-embedding-3-small', $pricing['openai']);
    }

    /**
     * Test that rate limits are configured per provider.
     */
    public function test_memory_config_rate_limits(): void
    {
        $rateLimits = config('memory.rate_limits');

        $this->assertIsArray($rateLimits);
        $this->assertArrayHasKey('openai', $rateLimits);
        $this->assertArrayHasKey('anthropic', $rateLimits);

        // Verify rate limit structure
        $openaiLimits = $rateLimits['openai'];
        $this->assertArrayHasKey('requests_per_minute', $openaiLimits);
        $this->assertArrayHasKey('tokens_per_minute', $openaiLimits);
    }
}
