<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\Enums\Runtime\RuntimeMode;
use App\Models\MemoryConversationLog;
use App\Models\MemoryCoreBlock;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\Adapters\MemoryToolAdapter;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\Exceptions\MemoryAccessDeniedException;
use App\Support\Memory\HybridRetriever;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MemoryToolAdapterTest extends TestCase
{
    use RefreshDatabase;

    private CoreMemoryManager $coreMemoryManager;

    private HybridRetriever $hybridRetriever;

    private Neo4jGraphStore $graphStore;

    private FeatureFlagManager $featureFlags;

    private MemoryToolAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coreMemoryManager = Mockery::mock(CoreMemoryManager::class);
        $this->hybridRetriever = Mockery::mock(HybridRetriever::class);
        $this->graphStore = Mockery::mock(Neo4jGraphStore::class);
        $this->featureFlags = Mockery::mock(FeatureFlagManager::class);

        $this->adapter = new MemoryToolAdapter(
            $this->coreMemoryManager,
            $this->hybridRetriever,
            $this->graphStore,
            $this->featureFlags,
        );
    }

    // --- Basic Adapter Contract ---

    public function test_name_returns_memory(): void
    {
        $this->assertEquals('memory', $this->adapter->name());
    }

    public function test_schema_includes_all_operations(): void
    {
        $schema = $this->adapter->schema();

        $this->assertArrayHasKey('operations', $schema);
        $this->assertContains('search_memories', $schema['operations']);
        $this->assertContains('get_core_block', $schema['operations']);
        $this->assertContains('set_core_block', $schema['operations']);
        $this->assertContains('list_core_blocks', $schema['operations']);
        $this->assertContains('get_entities', $schema['operations']);
        $this->assertContains('recall_conversations', $schema['operations']);
    }

    public function test_schema_has_description(): void
    {
        $schema = $this->adapter->schema();

        $this->assertArrayHasKey('description', $schema);
        $this->assertStringContainsString('memory', $schema['description']);
    }

    // --- Authorization / Capability Tests ---

    public function test_authorize_returns_false_when_memory_disabled(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(false);

        $context = $this->createContext(RuntimeMode::Standard);

        $this->assertFalse($this->adapter->authorize($context, ['operation' => 'search_memories']));
    }

    public function test_authorize_memory_read_allowed_in_safe_mode(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Safe);

        $this->assertTrue($this->adapter->authorize($context, ['operation' => 'search_memories']));
        $this->assertTrue($this->adapter->authorize($context, ['operation' => 'get_core_block']));
        $this->assertTrue($this->adapter->authorize($context, ['operation' => 'list_core_blocks']));
        $this->assertTrue($this->adapter->authorize($context, ['operation' => 'get_entities']));
        $this->assertTrue($this->adapter->authorize($context, ['operation' => 'recall_conversations']));
    }

    public function test_authorize_memory_write_denied_in_safe_mode(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Safe);

        $this->assertFalse($this->adapter->authorize($context, ['operation' => 'set_core_block']));
    }

    public function test_authorize_memory_write_allowed_in_standard_mode(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);

        $this->assertTrue($this->adapter->authorize($context, ['operation' => 'set_core_block']));
    }

    public function test_authorize_all_operations_allowed_in_full_mode(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Full);

        foreach (['search_memories', 'get_core_block', 'set_core_block', 'list_core_blocks', 'get_entities', 'recall_conversations'] as $operation) {
            $this->assertTrue(
                $this->adapter->authorize($context, ['operation' => $operation]),
                "Operation '$operation' should be authorized in full mode",
            );
        }
    }

    // --- Execute: Feature Flag Disabled ---

    public function test_execute_fails_when_memory_disabled(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(false);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['operation' => 'search_memories', 'query' => 'test']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('disabled', $result->error);
    }

    // --- Execute: Unknown Operation ---

    public function test_execute_fails_for_unknown_operation(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, ['operation' => 'unknown_op']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown memory operation', $result->error);
    }

    // --- search_memories ---

    public function test_search_memories_returns_results(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->hybridRetriever->shouldReceive('retrieve')
            ->once()
            ->withArgs(function ($userId, $query, $options) {
                return $query === 'deployment process' && $options['limit'] === 10;
            })
            ->andReturn([
                ['id' => 1, 'content' => 'Deploy to production via CI/CD', 'source' => 'conversation', 'rrf_score' => 0.85],
                ['id' => 2, 'content' => 'Use blue-green deployments', 'source' => 'entity', 'rrf_score' => 0.72],
            ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'search_memories',
            'query' => 'deployment process',
        ]);

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->data['results']);
        $this->assertEquals(2, $result->data['count']);
        $this->assertEquals('deployment process', $result->data['query']);
    }

    public function test_search_memories_respects_limit(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->hybridRetriever->shouldReceive('retrieve')
            ->once()
            ->withArgs(function ($userId, $query, $options) {
                return $options['limit'] === 5;
            })
            ->andReturn([]);

        $context = $this->createContext(RuntimeMode::Standard);
        $this->adapter->execute($context, [
            'operation' => 'search_memories',
            'query' => 'test',
            'limit' => 5,
        ]);
    }

    public function test_search_memories_caps_limit_at_50(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->hybridRetriever->shouldReceive('retrieve')
            ->once()
            ->withArgs(function ($userId, $query, $options) {
                return $options['limit'] === 50;
            })
            ->andReturn([]);

        $context = $this->createContext(RuntimeMode::Standard);
        $this->adapter->execute($context, [
            'operation' => 'search_memories',
            'query' => 'test',
            'limit' => 100,
        ]);
    }

    public function test_search_memories_requires_query(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'search_memories',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('query parameter is required', $result->error);
    }

    // --- get_core_block ---

    public function test_get_core_block_returns_existing_block(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $block = new MemoryCoreBlock;
        $block->block_key = 'task_state';
        $block->content_text = 'Working on feature X';
        $block->classification = 'internal';
        $block->version = 3;
        $block->updated_at = now();

        $this->coreMemoryManager->shouldReceive('get')
            ->once()
            ->andReturn($block);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_core_block',
            'key' => 'task_state',
        ]);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['exists']);
        $this->assertEquals('task_state', $result->data['key']);
        $this->assertEquals('Working on feature X', $result->data['content']);
        $this->assertEquals(3, $result->data['version']);
    }

    public function test_get_core_block_returns_not_found(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('get')
            ->once()
            ->andReturn(null);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_core_block',
            'key' => 'nonexistent',
        ]);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['exists']);
        $this->assertNull($result->data['content']);
    }

    public function test_get_core_block_requires_key(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_core_block',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('key parameter is required', $result->error);
    }

    // --- set_core_block ---

    public function test_set_core_block_writes_operational_block(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $block = new MemoryCoreBlock;
        $block->block_key = 'task_state';
        $block->version = 1;
        $block->updated_at = now();

        $this->coreMemoryManager->shouldReceive('set')
            ->once()
            ->withArgs(function ($userId, $key, $content, $jobId, $options) {
                return $key === 'task_state'
                    && $content === 'Working on feature Y'
                    && $options['actor'] === 'agent';
            })
            ->andReturn($block);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'set_core_block',
            'key' => 'task_state',
            'content' => 'Working on feature Y',
        ]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('updated successfully', $result->data['message']);
    }

    public function test_set_core_block_rejects_identity_block_writes(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->coreMemoryManager->shouldReceive('set')
            ->once()
            ->andThrow(new MemoryAccessDeniedException('Identity blocks can only be modified by the user'));

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'set_core_block',
            'key' => 'agent_persona',
            'content' => 'I am evil',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Cannot write', $result->error);
    }

    public function test_set_core_block_requires_key(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'set_core_block',
            'content' => 'some content',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('key parameter is required', $result->error);
    }

    public function test_set_core_block_requires_content(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'set_core_block',
            'key' => 'task_state',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('content parameter is required', $result->error);
    }

    // --- list_core_blocks ---

    public function test_list_core_blocks_returns_all_blocks(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $blocks = collect([
            tap(new MemoryCoreBlock, function ($b) {
                $b->block_key = 'agent_persona';
                $b->content_text = 'I am a helpful assistant';
                $b->classification = 'public';
                $b->version = 1;
                $b->updated_at = now();
            }),
            tap(new MemoryCoreBlock, function ($b) {
                $b->block_key = 'task_state';
                $b->content_text = 'Working on deployment automation';
                $b->classification = 'internal';
                $b->version = 5;
                $b->updated_at = now();
            }),
        ]);

        $this->coreMemoryManager->shouldReceive('listBlocks')
            ->once()
            ->andReturn($blocks);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'list_core_blocks',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->data['count']);
        $this->assertCount(2, $result->data['blocks']);
        $this->assertEquals('agent_persona', $result->data['blocks'][0]['key']);
        $this->assertEquals('task_state', $result->data['blocks'][1]['key']);
    }

    // --- get_entities ---

    public function test_get_entities_returns_related_entities(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->graphStore->shouldReceive('healthCheck')
            ->once()
            ->andReturn(true);

        $this->graphStore->shouldReceive('queryRelated')
            ->once()
            ->andReturn([
                ['id' => 'neo4j-1', 'type' => 'technology', 'name' => 'Laravel', 'distance' => 1, 'importance' => 0.9],
                ['id' => 'neo4j-2', 'type' => 'person', 'name' => 'Gareth', 'distance' => 2, 'importance' => 0.7],
            ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_entities',
            'query' => 'Laravel project',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->data['count']);
    }

    public function test_get_entities_filters_by_type(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->graphStore->shouldReceive('healthCheck')
            ->once()
            ->andReturn(true);

        $this->graphStore->shouldReceive('queryRelated')
            ->once()
            ->andReturn([
                ['id' => 'neo4j-1', 'type' => 'technology', 'name' => 'Laravel', 'distance' => 1, 'importance' => 0.9],
                ['id' => 'neo4j-2', 'type' => 'person', 'name' => 'Gareth', 'distance' => 2, 'importance' => 0.7],
            ]);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_entities',
            'query' => 'Laravel project',
            'type' => 'technology',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->data['count']);
        $this->assertEquals('Laravel', $result->data['entities'][0]['name']);
    }

    public function test_get_entities_handles_neo4j_unavailable(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->graphStore->shouldReceive('healthCheck')
            ->once()
            ->andReturn(false);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_entities',
            'query' => 'test',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->data['count']);
        $this->assertStringContainsString('not available', $result->data['message']);
    }

    public function test_get_entities_requires_query(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'get_entities',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('query parameter is required', $result->error);
    }

    // --- recall_conversations ---

    public function test_recall_conversations_searches_logs(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $user = User::factory()->create();
        $session = RuntimeSession::factory()->create(['user_id' => $user->id]);

        // Create conversation logs
        MemoryConversationLog::create([
            'user_id' => $user->id,
            'runtime_session_id' => $session->id,
            'role' => MemoryConversationLog::ROLE_ASSISTANT,
            'content' => 'The deployment pipeline uses GitHub Actions',
            'sequence' => 1,
            'event_type' => MemoryConversationLog::EVENT_MESSAGE,
            'classification' => 'internal',
        ]);

        MemoryConversationLog::create([
            'user_id' => $user->id,
            'runtime_session_id' => $session->id,
            'role' => MemoryConversationLog::ROLE_ASSISTANT,
            'content' => 'Unrelated conversation about lunch',
            'sequence' => 2,
            'event_type' => MemoryConversationLog::EVENT_MESSAGE,
            'classification' => 'internal',
        ]);

        $context = $this->createContextWithUser($user, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'recall_conversations',
            'query' => 'deployment',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->data['count']);
        $this->assertStringContainsString('deployment', $result->data['conversations'][0]['content_preview']);
    }

    public function test_recall_conversations_requires_query(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'recall_conversations',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('query parameter is required', $result->error);
    }

    public function test_recall_conversations_respects_user_isolation(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $session1 = RuntimeSession::factory()->create(['user_id' => $user1->id]);
        $session2 = RuntimeSession::factory()->create(['user_id' => $user2->id]);

        MemoryConversationLog::create([
            'user_id' => $user1->id,
            'runtime_session_id' => $session1->id,
            'role' => MemoryConversationLog::ROLE_ASSISTANT,
            'content' => 'Secret deployment info for user 1',
            'sequence' => 1,
            'event_type' => MemoryConversationLog::EVENT_MESSAGE,
            'classification' => 'confidential',
        ]);

        MemoryConversationLog::create([
            'user_id' => $user2->id,
            'runtime_session_id' => $session2->id,
            'role' => MemoryConversationLog::ROLE_ASSISTANT,
            'content' => 'Secret deployment info for user 2',
            'sequence' => 1,
            'event_type' => MemoryConversationLog::EVENT_MESSAGE,
            'classification' => 'confidential',
        ]);

        // User 2 should only see their own logs
        $context = $this->createContextWithUser($user2, RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'recall_conversations',
            'query' => 'deployment',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->data['count']);
        $this->assertStringContainsString('user 2', $result->data['conversations'][0]['content_preview']);
    }

    // --- Error Handling ---

    public function test_execute_catches_throwable_exceptions(): void
    {
        $this->featureFlags->shouldReceive('enabled')
            ->with(FeatureFlagManager::MEMORY_ENABLED)
            ->andReturn(true);

        $this->hybridRetriever->shouldReceive('retrieve')
            ->once()
            ->andThrow(new \RuntimeException('Database connection lost'));

        $context = $this->createContext(RuntimeMode::Standard);
        $result = $this->adapter->execute($context, [
            'operation' => 'search_memories',
            'query' => 'test',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Database connection lost', $result->error);
    }

    // --- Helpers ---

    private function createContext(RuntimeMode $mode): RuntimeContext
    {
        $session = RuntimeSession::factory()->make(['mode' => $mode]);
        $turn = RuntimeTurn::factory()->make();
        $user = User::factory()->make(['id' => 1]);

        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: $mode,
            policy: config("runtime.modes.{$mode->value}"),
            workspaceRoot: '/tmp',
        );
    }

    private function createContextWithUser(User $user, RuntimeMode $mode): RuntimeContext
    {
        $session = RuntimeSession::factory()->make(['mode' => $mode]);
        $turn = RuntimeTurn::factory()->make();

        return new RuntimeContext(
            session: $session,
            turn: $turn,
            user: $user,
            mode: $mode,
            policy: config("runtime.modes.{$mode->value}"),
            workspaceRoot: '/tmp',
        );
    }
}
