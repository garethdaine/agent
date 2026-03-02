<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use App\Models\MemoryProviderUsage;
use App\Models\User;
use App\Support\Memory\ProviderUsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Tests for ProviderUsageTracker usage tracking and cost estimation.
 *
 * Verifies:
 * - Records input/output tokens
 * - Calculates cost from pricing table
 * - Never includes API keys in logged data
 * - Aggregates usage per user
 */
class ProviderUsageTrackerTest extends TestCase
{
    use RefreshDatabase;

    private ProviderUsageTracker $tracker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.enabled' => true]);

        $this->tracker = new ProviderUsageTracker;
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that input/output tokens are recorded correctly.
     */
    public function test_records_input_output_tokens(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $operation = 'extraction';
        $inputTokens = 500;
        $outputTokens = 200;

        $usage = $this->tracker->record(
            $this->user->id,
            $provider,
            $model,
            $operation,
            $inputTokens,
            $outputTokens
        );

        $this->assertInstanceOf(MemoryProviderUsage::class, $usage);
        $this->assertEquals($this->user->id, $usage->user_id);
        $this->assertEquals($provider, $usage->provider);
        $this->assertEquals($model, $usage->model);
        $this->assertEquals($operation, $usage->operation);
        $this->assertEquals($inputTokens, $usage->input_tokens);
        $this->assertEquals($outputTokens, $usage->output_tokens);
    }

    /**
     * Test that null output tokens are handled (for embeddings).
     */
    public function test_handles_null_output_tokens_for_embeddings(): void
    {
        $provider = 'openai';
        $model = 'text-embedding-3-small';
        $operation = 'embedding';
        $inputTokens = 1000;

        $usage = $this->tracker->record(
            $this->user->id,
            $provider,
            $model,
            $operation,
            $inputTokens,
            null
        );

        $this->assertEquals($inputTokens, $usage->input_tokens);
        $this->assertNull($usage->output_tokens);
    }

    /**
     * Test that cost is calculated from pricing table.
     */
    public function test_calculates_cost_from_pricing_table(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $operation = 'extraction';
        $inputTokens = 1_000_000; // 1M tokens
        $outputTokens = 500_000; // 500K tokens

        // Config pricing: gpt-4o-mini input=0.15, output=0.60 per 1M
        $expectedCost = (1_000_000 / 1_000_000) * 0.15 + (500_000 / 1_000_000) * 0.60;

        $usage = $this->tracker->record(
            $this->user->id,
            $provider,
            $model,
            $operation,
            $inputTokens,
            $outputTokens
        );

        $this->assertEquals($expectedCost, $usage->cost_estimate_usd);
    }

    /**
     * Test that embedding model cost calculation is correct (input only).
     */
    public function test_embedding_cost_calculation_input_only(): void
    {
        $provider = 'openai';
        $model = 'text-embedding-3-small';
        $operation = 'embedding';
        $inputTokens = 1_000_000; // 1M tokens

        // Config pricing: text-embedding-3-small input=0.02 per 1M
        $expectedCost = (1_000_000 / 1_000_000) * 0.02;

        $usage = $this->tracker->record(
            $this->user->id,
            $provider,
            $model,
            $operation,
            $inputTokens,
            null
        );

        $this->assertEquals($expectedCost, $usage->cost_estimate_usd);
    }

    /**
     * Test that unknown model returns zero cost.
     */
    public function test_unknown_model_returns_zero_cost(): void
    {
        $provider = 'openai';
        $model = 'unknown-model';
        $operation = 'extraction';
        $inputTokens = 1000;
        $outputTokens = 500;

        $usage = $this->tracker->record(
            $this->user->id,
            $provider,
            $model,
            $operation,
            $inputTokens,
            $outputTokens
        );

        $this->assertEquals(0.0, $usage->cost_estimate_usd);
    }

    /**
     * Test that API keys are never included in logged data.
     */
    public function test_never_includes_api_keys_in_logged_data(): void
    {
        $apiKey = 'sk-test-secret-api-key-12345';

        // Get loggable context - should never contain keys
        $loggable = $this->tracker->getLoggableContext([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => $apiKey,
            'authorization' => "Bearer $apiKey",
        ]);

        $serialized = json_encode($loggable);
        $this->assertStringNotContainsString($apiKey, $serialized);
        $this->assertStringNotContainsString('sk-test', $serialized);
        $this->assertStringNotContainsString('Bearer', $serialized);
    }

    /**
     * Test that getLoggableContext strips sensitive fields.
     */
    public function test_get_loggable_context_strips_sensitive_fields(): void
    {
        $context = [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-secret-key',
            'authorization' => 'Bearer sk-secret-key',
            'apiKey' => 'another-secret',
            'secret' => 'should-be-removed',
            'token' => 'should-be-removed',
            'password' => 'should-be-removed',
            'input_tokens' => 100,
        ];

        $loggable = $this->tracker->getLoggableContext($context);

        $this->assertArrayHasKey('provider', $loggable);
        $this->assertArrayHasKey('model', $loggable);
        $this->assertArrayHasKey('input_tokens', $loggable);
        $this->assertArrayNotHasKey('api_key', $loggable);
        $this->assertArrayNotHasKey('authorization', $loggable);
        $this->assertArrayNotHasKey('apiKey', $loggable);
        $this->assertArrayNotHasKey('secret', $loggable);
        $this->assertArrayNotHasKey('token', $loggable);
        $this->assertArrayNotHasKey('password', $loggable);
    }

    /**
     * Test that usage is aggregated per user.
     */
    public function test_aggregates_usage_per_user(): void
    {
        $user2 = User::factory()->create();
        $provider = 'openai';
        $model = 'gpt-4o-mini';

        // Record usage for user 1
        $this->tracker->record($this->user->id, $provider, $model, 'extraction', 1000, 500);
        $this->tracker->record($this->user->id, $provider, $model, 'summarization', 2000, 1000);

        // Record usage for user 2
        $this->tracker->record($user2->id, $provider, $model, 'extraction', 500, 250);

        // Get aggregated stats for user 1
        $stats1 = $this->tracker->getUsageStats($this->user->id);

        $this->assertEquals(4500, $stats1['total_tokens']); // 1000+500+2000+1000
        $this->assertGreaterThan(0, $stats1['total_cost']);
        $this->assertArrayHasKey('openai', $stats1['by_provider']);

        // Get aggregated stats for user 2
        $stats2 = $this->tracker->getUsageStats($user2->id);

        $this->assertEquals(750, $stats2['total_tokens']); // 500+250
    }

    /**
     * Test that usage stats can be filtered by date.
     */
    public function test_usage_stats_filtered_by_date(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';

        // Record some usage
        $this->tracker->record($this->user->id, $provider, $model, 'extraction', 1000, 500);

        // Get stats for today
        $statsToday = $this->tracker->getUsageStats($this->user->id, now()->startOfDay());

        $this->assertEquals(1500, $statsToday['total_tokens']);

        // Get stats for yesterday (should be empty)
        $statsYesterday = $this->tracker->getUsageStats($this->user->id, now()->subDay()->startOfDay(), now()->subDay()->endOfDay());

        $this->assertEquals(0, $statsYesterday['total_tokens']);
    }

    /**
     * Test that usage stats are broken down by provider.
     */
    public function test_usage_stats_breakdown_by_provider(): void
    {
        // Record usage for different providers
        $this->tracker->record($this->user->id, 'openai', 'gpt-4o-mini', 'extraction', 1000, 500);
        $this->tracker->record($this->user->id, 'anthropic', 'claude-3-haiku-20240307', 'extraction', 800, 400);

        $stats = $this->tracker->getUsageStats($this->user->id);

        $this->assertArrayHasKey('openai', $stats['by_provider']);
        $this->assertArrayHasKey('anthropic', $stats['by_provider']);
        $this->assertEquals(1500, $stats['by_provider']['openai']['tokens']);
        $this->assertEquals(1200, $stats['by_provider']['anthropic']['tokens']);
    }

    /**
     * Test that usage stats are broken down by operation.
     */
    public function test_usage_stats_breakdown_by_operation(): void
    {
        $provider = 'openai';

        $this->tracker->record($this->user->id, $provider, 'gpt-4o-mini', 'extraction', 1000, 500);
        $this->tracker->record($this->user->id, $provider, 'gpt-4.1-nano', 'summarization', 2000, 1000);
        $this->tracker->record($this->user->id, $provider, 'text-embedding-3-small', 'embedding', 500, null);

        $stats = $this->tracker->getUsageStats($this->user->id);

        $this->assertArrayHasKey('by_operation', $stats);
        $this->assertArrayHasKey('extraction', $stats['by_operation']);
        $this->assertArrayHasKey('summarization', $stats['by_operation']);
        $this->assertArrayHasKey('embedding', $stats['by_operation']);
    }

    /**
     * Test that run_id is recorded when provided.
     */
    public function test_records_run_id_when_provided(): void
    {
        // Create an agent job and run for the foreign key constraint
        $job = \App\Models\AgentJob::factory()->create(['user_id' => $this->user->id]);
        $run = \App\Models\AgentJobRun::factory()->create(['agent_job_id' => $job->id, 'user_id' => $this->user->id]);

        $usage = $this->tracker->record(
            $this->user->id,
            'openai',
            'gpt-4o-mini',
            'extraction',
            100,
            50,
            $run->id
        );

        $this->assertEquals($run->id, $usage->run_id);
    }

    /**
     * Test that estimateCost returns correct estimate without recording.
     */
    public function test_estimate_cost_returns_correct_estimate(): void
    {
        $provider = 'openai';
        $model = 'gpt-4o-mini';
        $inputTokens = 1_000_000;
        $outputTokens = 500_000;

        // Config pricing: gpt-4o-mini input=0.15, output=0.60 per 1M
        $expectedCost = 0.15 + 0.30; // 0.45

        $estimate = $this->tracker->estimateCost($provider, $model, $inputTokens, $outputTokens);

        $this->assertEquals($expectedCost, $estimate);
    }

    /**
     * Test that Anthropic pricing is calculated correctly.
     */
    public function test_anthropic_pricing_calculated_correctly(): void
    {
        $provider = 'anthropic';
        $model = 'claude-3-haiku-20240307';
        $inputTokens = 1_000_000;
        $outputTokens = 500_000;

        // Config pricing: claude-3-haiku input=0.25, output=1.25 per 1M
        $expectedCost = (1_000_000 / 1_000_000) * 0.25 + (500_000 / 1_000_000) * 1.25;

        $usage = $this->tracker->record(
            $this->user->id,
            $provider,
            $model,
            'extraction',
            $inputTokens,
            $outputTokens
        );

        $this->assertEquals($expectedCost, $usage->cost_estimate_usd);
    }

    /**
     * Test that getTotalCost returns sum of all costs for user.
     */
    public function test_get_total_cost_returns_sum_for_user(): void
    {
        // Record multiple usages
        $this->tracker->record($this->user->id, 'openai', 'gpt-4o-mini', 'extraction', 1_000_000, 500_000);
        $this->tracker->record($this->user->id, 'openai', 'text-embedding-3-small', 'embedding', 1_000_000, null);

        $totalCost = $this->tracker->getTotalCost($this->user->id);

        // gpt-4o-mini: 0.15 + 0.30 = 0.45
        // text-embedding-3-small: 0.02
        // Total: 0.47
        // Use delta comparison for floating point
        $this->assertEqualsWithDelta(0.47, $totalCost, 0.0001);
    }
}
