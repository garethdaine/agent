<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Models\AgentJob;
use App\Models\MemoryCoreBlock;
use App\Models\User;
use App\Support\Memory\CoreMemoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CoreMemoryManager Layer 1 CRUD operations.
 *
 * Verifies:
 * - get() returns null for non-existent block
 * - set() creates new block with version 1
 * - set() on existing block increments version
 * - Identity block rejects agent write (simulated via context flag)
 * - Operational block accepts any valid JSON
 * - Classification defaults to 'internal'
 * - delete() removes block
 * - All operations scoped to user_id
 */
class CoreMemoryManagerTest extends TestCase
{
    use RefreshDatabase;

    private CoreMemoryManager $manager;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable memory for these tests
        config(['memory.enabled' => true]);

        $this->user = User::factory()->create();
        $this->manager = app(CoreMemoryManager::class);
    }

    /**
     * Test that get() returns null for non-existent block.
     */
    public function test_get_returns_null_for_non_existent_block(): void
    {
        $result = $this->manager->get($this->user->id, 'agent_persona');

        $this->assertNull($result);
    }

    /**
     * Test that set() creates new block with version 1.
     */
    public function test_set_creates_new_block_with_version_1(): void
    {
        $block = $this->manager->set(
            $this->user->id,
            'agent_persona',
            'I am a helpful assistant.',
        );

        $this->assertInstanceOf(MemoryCoreBlock::class, $block);
        $this->assertEquals(1, $block->version);
        $this->assertEquals($this->user->id, $block->user_id);
        $this->assertEquals('agent_persona', $block->block_key);
        $this->assertEquals('I am a helpful assistant.', $block->content_text);
    }

    /**
     * Test that set() on existing block increments version.
     */
    public function test_set_on_existing_block_increments_version(): void
    {
        // Create initial block
        $this->manager->set($this->user->id, 'agent_persona', 'Version 1');

        // Update the block
        $block = $this->manager->set($this->user->id, 'agent_persona', 'Version 2');

        $this->assertEquals(2, $block->version);
        $this->assertEquals('Version 2', $block->content_text);
    }

    /**
     * Test that identity block rejects agent write.
     */
    public function test_identity_block_rejects_agent_write(): void
    {
        $this->expectException(\App\Support\Memory\Exceptions\MemoryAccessDeniedException::class);
        $this->expectExceptionMessage('Identity blocks cannot be modified by agents');

        $this->manager->set(
            $this->user->id,
            'agent_persona',
            'Trying to change persona',
            null,
            ['actor' => 'agent'],
        );
    }

    /**
     * Test that identity block accepts user write.
     */
    public function test_identity_block_accepts_user_write(): void
    {
        $block = $this->manager->set(
            $this->user->id,
            'agent_persona',
            'User-set persona',
            null,
            ['actor' => 'user'],
        );

        $this->assertEquals('User-set persona', $block->content_text);
    }

    /**
     * Test that operational block accepts any valid JSON.
     */
    public function test_operational_block_accepts_any_valid_json(): void
    {
        $data = [
            'current_step' => 3,
            'completed_steps' => ['init', 'fetch', 'process'],
            'metadata' => [
                'started_at' => '2024-01-01T00:00:00Z',
                'nested' => ['deep' => true],
            ],
        ];

        $block = $this->manager->set(
            $this->user->id,
            'task_state',
            $data,
        );

        $this->assertInstanceOf(MemoryCoreBlock::class, $block);
        $this->assertEquals($data, $block->content_json);
        $this->assertNull($block->content_text);
    }

    /**
     * Test that operational block allows agent write.
     */
    public function test_operational_block_allows_agent_write(): void
    {
        $block = $this->manager->set(
            $this->user->id,
            'task_state',
            ['step' => 1],
            null,
            ['actor' => 'agent'],
        );

        $this->assertEquals(['step' => 1], $block->content_json);
    }

    /**
     * Test that classification defaults to 'internal'.
     */
    public function test_classification_defaults_to_internal(): void
    {
        $block = $this->manager->set(
            $this->user->id,
            'task_state',
            ['step' => 1],
        );

        $this->assertEquals(MemoryCoreBlock::CLASSIFICATION_INTERNAL, $block->classification);
    }

    /**
     * Test that classification can be set explicitly.
     */
    public function test_classification_can_be_set_explicitly(): void
    {
        $block = $this->manager->set(
            $this->user->id,
            'task_state',
            ['step' => 1],
            null,
            ['classification' => MemoryCoreBlock::CLASSIFICATION_CONFIDENTIAL],
        );

        $this->assertEquals(MemoryCoreBlock::CLASSIFICATION_CONFIDENTIAL, $block->classification);
    }

    /**
     * Test that delete() removes block.
     */
    public function test_delete_removes_block(): void
    {
        $this->manager->set($this->user->id, 'task_state', ['step' => 1]);

        $result = $this->manager->delete($this->user->id, 'task_state');

        $this->assertTrue($result);
        $this->assertNull($this->manager->get($this->user->id, 'task_state'));
    }

    /**
     * Test that delete() returns false for non-existent block.
     */
    public function test_delete_returns_false_for_non_existent_block(): void
    {
        $result = $this->manager->delete($this->user->id, 'task_state');

        $this->assertFalse($result);
    }

    /**
     * Test that operations are scoped to user_id.
     */
    public function test_operations_scoped_to_user_id(): void
    {
        $otherUser = User::factory()->create();

        // Create block for first user
        $this->manager->set($this->user->id, 'task_state', ['user' => 1]);

        // Create block for second user
        $this->manager->set($otherUser->id, 'task_state', ['user' => 2]);

        // Get should only return own user's block
        $block1 = $this->manager->get($this->user->id, 'task_state');
        $block2 = $this->manager->get($otherUser->id, 'task_state');

        $this->assertEquals(['user' => 1], $block1->content_json);
        $this->assertEquals(['user' => 2], $block2->content_json);
    }

    /**
     * Test that listBlocks returns all blocks for user.
     */
    public function test_list_blocks_returns_all_blocks_for_user(): void
    {
        $this->manager->set($this->user->id, 'task_state', ['step' => 1]);
        $this->manager->set($this->user->id, 'active_goals', ['goal1']);

        $blocks = $this->manager->listBlocks($this->user->id);

        $this->assertCount(2, $blocks);
        $this->assertTrue($blocks->contains('block_key', 'task_state'));
        $this->assertTrue($blocks->contains('block_key', 'active_goals'));
    }

    /**
     * Test that job-scoped blocks are isolated from global blocks.
     */
    public function test_job_scoped_blocks_are_isolated(): void
    {
        $job = AgentJob::factory()->for($this->user)->create();

        // Create global block
        $this->manager->set($this->user->id, 'task_state', ['scope' => 'global']);

        // Create job-scoped block
        $this->manager->set($this->user->id, 'task_state', ['scope' => 'job'], $job->id);

        // Get global block
        $globalBlock = $this->manager->get($this->user->id, 'task_state');
        $this->assertEquals(['scope' => 'global'], $globalBlock->content_json);

        // Get job-scoped block
        $jobBlock = $this->manager->get($this->user->id, 'task_state', $job->id);
        $this->assertEquals(['scope' => 'job'], $jobBlock->content_json);
    }

    /**
     * Test that listBlocks with jobId returns only job-scoped blocks.
     */
    public function test_list_blocks_with_job_id_returns_only_job_scoped_blocks(): void
    {
        $job = AgentJob::factory()->for($this->user)->create();

        // Create global block
        $this->manager->set($this->user->id, 'task_state', ['scope' => 'global']);

        // Create job-scoped block
        $this->manager->set($this->user->id, 'active_goals', ['scope' => 'job'], $job->id);

        $globalBlocks = $this->manager->listBlocks($this->user->id);
        $jobBlocks = $this->manager->listBlocks($this->user->id, $job->id);

        $this->assertCount(1, $globalBlocks);
        $this->assertCount(1, $jobBlocks);
        $this->assertEquals('task_state', $globalBlocks->first()->block_key);
        $this->assertEquals('active_goals', $jobBlocks->first()->block_key);
    }

    /**
     * Test that AuditLogger records mutations.
     */
    public function test_audit_logger_records_mutations(): void
    {
        $this->manager->set($this->user->id, 'task_state', ['step' => 1]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'memory_core_block_created',
            'target_type' => 'memory_core_block',
        ]);
    }

    /**
     * Test that AuditLogger records updates.
     */
    public function test_audit_logger_records_updates(): void
    {
        $this->manager->set($this->user->id, 'task_state', ['step' => 1]);
        $this->manager->set($this->user->id, 'task_state', ['step' => 2]);

        $this->assertDatabaseHas('agent_audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'memory_core_block_updated',
            'target_type' => 'memory_core_block',
        ]);
    }

    /**
     * Test that AuditLogger records deletes.
     */
    public function test_audit_logger_records_deletes(): void
    {
        $this->manager->set($this->user->id, 'task_state', ['step' => 1]);
        $this->manager->delete($this->user->id, 'task_state');

        $this->assertDatabaseHas('agent_audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'memory_core_block_deleted',
            'target_type' => 'memory_core_block',
        ]);
    }

    /**
     * Test that invalid block key is rejected.
     */
    public function test_invalid_block_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid block key');

        $this->manager->set($this->user->id, 'invalid_block_key', 'content');
    }

    /**
     * Test that agent cannot downgrade classification.
     */
    public function test_agent_cannot_downgrade_classification(): void
    {
        // Create with confidential classification
        $this->manager->set(
            $this->user->id,
            'task_state',
            ['data' => 'secret'],
            null,
            ['classification' => MemoryCoreBlock::CLASSIFICATION_CONFIDENTIAL],
        );

        // Agent tries to downgrade to internal
        $this->expectException(\App\Support\Memory\Exceptions\MemoryAccessDeniedException::class);
        $this->expectExceptionMessage('Agents cannot downgrade classification');

        $this->manager->set(
            $this->user->id,
            'task_state',
            ['data' => 'not secret'],
            null,
            [
                'actor' => 'agent',
                'classification' => MemoryCoreBlock::CLASSIFICATION_INTERNAL,
            ],
        );
    }

    /**
     * Test that user can downgrade classification.
     */
    public function test_user_can_downgrade_classification(): void
    {
        // Create with confidential classification
        $this->manager->set(
            $this->user->id,
            'task_state',
            ['data' => 'secret'],
            null,
            ['classification' => MemoryCoreBlock::CLASSIFICATION_CONFIDENTIAL],
        );

        // User can downgrade to internal
        $block = $this->manager->set(
            $this->user->id,
            'task_state',
            ['data' => 'not secret anymore'],
            null,
            [
                'actor' => 'user',
                'classification' => MemoryCoreBlock::CLASSIFICATION_INTERNAL,
            ],
        );

        $this->assertEquals(MemoryCoreBlock::CLASSIFICATION_INTERNAL, $block->classification);
    }
}
