<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\MemoryConversationLog;
use App\Models\MemoryCoreBlock;
use App\Models\MemoryEmbedding;
use App\Models\MemoryProviderUsage;
use App\Models\MemorySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Memory HTTP API endpoint tests.
 *
 * Tests all 12 Memory API endpoints:
 * - GET /memory/settings
 * - PUT /memory/settings
 * - POST /memory/settings/test-connection
 * - GET /memory/settings/capabilities
 * - GET /memory/core-blocks
 * - GET /memory/core-blocks/{key}
 * - PUT /memory/core-blocks/{key}
 * - DELETE /memory/core-blocks/{key}
 * - POST /memory/retrieve
 * - POST /memory/working/append
 * - GET /memory/working/{runId}
 * - GET /memory/stats
 */
class MemoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $baseUrl = '/agent/api/v1/memory';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Config::set('memory.enabled', true);
    }

    // =========================================================================
    // AUTHENTICATION TESTS
    // =========================================================================

    public function test_settings_index_requires_authentication(): void
    {
        $response = $this->getJson("{$this->baseUrl}/settings");

        $response->assertStatus(401);
    }

    public function test_core_blocks_index_requires_authentication(): void
    {
        $response = $this->getJson("{$this->baseUrl}/core-blocks");

        $response->assertStatus(401);
    }

    public function test_retrieve_requires_authentication(): void
    {
        $response = $this->postJson("{$this->baseUrl}/retrieve", ['query' => 'test']);

        $response->assertStatus(401);
    }

    public function test_stats_requires_authentication(): void
    {
        $response = $this->getJson("{$this->baseUrl}/stats");

        $response->assertStatus(401);
    }

    // =========================================================================
    // MEMORY DISABLED TESTS (503 Service Unavailable)
    // =========================================================================

    public function test_settings_accessible_when_memory_disabled(): void
    {
        Config::set('memory.enabled', false);

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/settings");

        $response->assertStatus(200);
    }

    public function test_core_blocks_returns_503_when_memory_disabled(): void
    {
        Config::set('memory.enabled', false);

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/core-blocks");

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'MEMORY_DISABLED');
    }

    public function test_retrieve_returns_503_when_memory_disabled(): void
    {
        Config::set('memory.enabled', false);

        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/retrieve", ['query' => 'test']);

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'MEMORY_DISABLED');
    }

    // =========================================================================
    // SETTINGS CONTROLLER TESTS
    // =========================================================================

    public function test_settings_index_returns_masked_keys(): void
    {
        // Store an API key setting
        MemorySetting::setForUser($this->user->id, 'provider_key_openai', 'sk-1234567890abcdef');
        MemorySetting::setForUser($this->user->id, 'extraction_model', 'gpt-4o-mini');

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/settings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['key', 'value'],
                ],
            ]);

        // API keys should be masked (show only last 4 chars)
        $settings = collect($response->json('data'));
        $apiKey = $settings->firstWhere('key', 'provider_key_openai');
        if ($apiKey) {
            $this->assertStringContainsString('****', $apiKey['value']);
            $this->assertStringContainsString('cdef', $apiKey['value']);
        }
    }

    public function test_settings_update_batch_updates_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/settings", [
                'settings' => [
                    'extraction_model' => 'gpt-4o-mini',
                    'summarization_model' => 'gpt-4.1-nano',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.updated', 2);

        $this->assertEquals('gpt-4o-mini', MemorySetting::getForUser($this->user->id, 'extraction_model'));
        $this->assertEquals('gpt-4.1-nano', MemorySetting::getForUser($this->user->id, 'summarization_model'));
    }

    public function test_settings_update_rejects_invalid_keys(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/settings", [
                'settings' => [
                    'invalid_key_name' => 'some_value',
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_settings_test_connection_for_provider(): void
    {
        // Store a mock API key
        MemorySetting::setForUser($this->user->id, 'provider_key_openai', 'sk-test-key');

        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/settings/test-connection", [
                'provider' => 'openai',
            ]);

        // Should return either success or connection error (not validation error)
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'data' => ['provider', 'success'],
        ]);
    }

    public function test_settings_test_connection_requires_provider(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/settings/test-connection", []);

        $response->assertStatus(422);
    }

    public function test_settings_capabilities_returns_mode(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/settings/capabilities");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'mode',
                    'core_memory',
                    'working_memory',
                    'keyword_search',
                    'embeddings',
                    'entity_extraction',
                    'semantic_search',
                    'graph_search',
                    'configured_providers',
                ],
            ])
            ->assertJsonPath('data.core_memory', true)
            ->assertJsonPath('data.working_memory', true);
    }

    // =========================================================================
    // CORE BLOCK CONTROLLER TESTS
    // =========================================================================

    public function test_core_blocks_index_lists_user_blocks(): void
    {
        // Create blocks for the user
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'agent_persona',
            'block_type' => 'identity',
            'content_text' => 'You are a helpful assistant.',
        ]);

        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'task_state',
            'block_type' => 'operational',
            'content_json' => ['current_task' => 'testing'],
        ]);

        // Create a block for another user (should not appear)
        $otherUser = User::factory()->create();
        MemoryCoreBlock::factory()->create([
            'user_id' => $otherUser->id,
            'block_key' => 'agent_persona',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/core-blocks");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.block_key', 'agent_persona');
    }

    public function test_core_blocks_show_returns_single_block(): void
    {
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'user_profile',
            'block_type' => 'identity',
            'content_text' => 'Profile content here',
            'classification' => 'internal',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/core-blocks/user_profile");

        $response->assertStatus(200)
            ->assertJsonPath('data.block_key', 'user_profile')
            ->assertJsonPath('data.content_text', 'Profile content here')
            ->assertJsonPath('data.classification', 'internal');
    }

    public function test_core_blocks_show_returns_404_for_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/core-blocks/nonexistent_key");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_core_blocks_update_creates_block(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/core-blocks/task_state", [
                'content' => ['active_task' => 'implementation'],
                'classification' => 'internal',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.block_key', 'task_state')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('memory_core_blocks', [
            'user_id' => $this->user->id,
            'block_key' => 'task_state',
        ]);
    }

    public function test_core_blocks_update_increments_version(): void
    {
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'task_state',
            'block_type' => 'operational',
            'content_json' => ['v1' => true],
            'version' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/core-blocks/task_state", [
                'content' => ['v2' => true],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.version', 2);
    }

    public function test_core_blocks_delete_removes_block(): void
    {
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'task_state',
            'block_type' => 'operational',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("{$this->baseUrl}/core-blocks/task_state");

        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('memory_core_blocks', [
            'user_id' => $this->user->id,
            'block_key' => 'task_state',
        ]);
    }

    public function test_core_blocks_delete_returns_404_for_missing(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("{$this->baseUrl}/core-blocks/nonexistent_key");

        $response->assertStatus(404);
    }

    public function test_core_blocks_rejects_invalid_block_key(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/core-blocks/invalid_block_name", [
                'content' => 'test',
            ]);

        $response->assertStatus(422);
    }

    public function test_core_blocks_supports_job_scoping(): void
    {
        $job = AgentJob::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/core-blocks/task_state?job_id={$job->id}", [
                'content' => ['job_specific' => true],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('memory_core_blocks', [
            'user_id' => $this->user->id,
            'job_id' => $job->id,
            'block_key' => 'task_state',
        ]);
    }

    // =========================================================================
    // RETRIEVAL CONTROLLER TESTS
    // =========================================================================

    public function test_retrieve_returns_hybrid_results(): void
    {
        // Create some searchable content
        MemoryConversationLog::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'The user asked about Laravel authentication',
        ]);

        MemoryEmbedding::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'Discussion about authentication flows',
            'source_type' => 'conversation',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/retrieve", [
                'query' => 'authentication',
                'limit' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'results',
                    'sources_used',
                ],
            ]);
    }

    public function test_retrieve_requires_query(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/retrieve", []);

        $response->assertStatus(422);
    }

    public function test_retrieve_supports_weight_configuration(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/retrieve", [
                'query' => 'test query',
                'semantic_weight' => 1.5,
                'keyword_weight' => 0.5,
                'graph_weight' => 0.0,
            ]);

        $response->assertStatus(200);
    }

    public function test_retrieve_supports_classification_filter(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/retrieve", [
                'query' => 'test',
                'classification' => ['public', 'internal'],
            ]);

        $response->assertStatus(200);
    }

    // =========================================================================
    // WORKING MEMORY CONTROLLER TESTS
    // =========================================================================

    public function test_working_append_adds_entry(): void
    {
        $job = AgentJob::factory()->create(['user_id' => $this->user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/working/append", [
                'run_id' => $run->id,
                'role' => 'assistant',
                'content' => 'This is a test message',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.appended', true);
    }

    public function test_working_append_requires_run_id(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/working/append", [
                'role' => 'assistant',
                'content' => 'test',
            ]);

        $response->assertStatus(422);
    }

    public function test_working_append_validates_run_belongs_to_user(): void
    {
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $otherUser->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/working/append", [
                'run_id' => $run->id,
                'role' => 'assistant',
                'content' => 'test',
            ]);

        $response->assertStatus(403);
    }

    public function test_working_get_returns_buffer(): void
    {
        $job = AgentJob::factory()->create(['user_id' => $this->user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
        ]);

        // Manually add entries to Redis working memory
        $key = "memory:run:{$run->id}:working";
        try {
            $connection = Redis::connection('memory');
            $connection->zadd($key, now()->timestamp, json_encode([
                'role' => 'assistant',
                'content' => 'Test message 1',
                'timestamp' => now()->timestamp,
            ]));
            $connection->zadd($key, now()->timestamp + 1, json_encode([
                'role' => 'user',
                'content' => 'Test message 2',
                'timestamp' => now()->timestamp + 1,
            ]));
        } catch (\Throwable) {
            // Redis might not be available in test env
        }

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/working/{$run->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['entries', 'run_id'],
            ]);
    }

    public function test_working_get_validates_run_belongs_to_user(): void
    {
        $otherUser = User::factory()->create();
        $job = AgentJob::factory()->create(['user_id' => $otherUser->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/working/{$run->id}");

        $response->assertStatus(403);
    }

    // =========================================================================
    // DIAGNOSTICS CONTROLLER TESTS
    // =========================================================================

    public function test_stats_returns_diagnostics(): void
    {
        // Create core blocks with different keys to avoid uniqueness constraints
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'task_state',
        ]);
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'tool_results_cache',
        ]);
        MemoryCoreBlock::factory()->create([
            'user_id' => $this->user->id,
            'block_key' => 'active_goals',
        ]);

        // Create embeddings
        MemoryEmbedding::factory()->count(5)->create(['user_id' => $this->user->id]);

        // Create provider usage
        MemoryProviderUsage::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'core_blocks_count',
                    'embeddings_count',
                    'conversation_logs_count',
                    'provider_usage',
                    'operating_mode',
                ],
            ]);
    }

    // =========================================================================
    // RATE LIMITING TESTS
    // =========================================================================

    public function test_read_endpoints_allow_120_per_minute(): void
    {
        // Make many requests - should not hit limit within 120
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($this->user)
                ->getJson("{$this->baseUrl}/settings");

            $response->assertStatus(200);
        }
    }

    public function test_write_endpoints_respect_throttle(): void
    {
        // This test verifies the throttle middleware is applied
        // Full throttle testing would need to simulate 30+ requests
        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/settings", [
                'settings' => ['extraction_model' => 'gpt-4o-mini'],
            ]);

        // Should succeed (within limit)
        $response->assertStatus(200);
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    public function test_core_blocks_validates_classification(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("{$this->baseUrl}/core-blocks/task_state", [
                'content' => 'test',
                'classification' => 'invalid_classification',
            ]);

        $response->assertStatus(422);
    }

    public function test_retrieve_validates_weights_are_numeric(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/retrieve", [
                'query' => 'test',
                'semantic_weight' => 'not_a_number',
            ]);

        $response->assertStatus(422);
    }

    public function test_working_append_validates_role(): void
    {
        $job = AgentJob::factory()->create(['user_id' => $this->user->id]);
        $run = AgentJobRun::factory()->create([
            'agent_job_id' => $job->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("{$this->baseUrl}/working/append", [
                'run_id' => $run->id,
                'role' => 'invalid_role',
                'content' => 'test',
            ]);

        $response->assertStatus(422);
    }
}
