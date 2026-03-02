<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\MemoryCoreBlock;
use App\Support\Agent\AuditLogger;
use App\Support\Memory\Exceptions\MemoryAccessDeniedException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Core Memory Manager (Layer 1).
 *
 * Provides CRUD operations for memory core blocks with:
 * - Version tracking (auto-increment on update)
 * - Classification management (public, internal, confidential)
 * - Agent write restrictions for identity blocks
 * - Audit logging for all mutations
 * - User and optional job scoping
 *
 * Block Keys:
 * - agent_persona (identity, text, user-only)
 * - user_profile (identity, text, user-only)
 * - task_state (operational, json, agent-editable)
 * - tool_results_cache (operational, json, agent-editable)
 * - active_goals (operational, json, agent-editable)
 */
class CoreMemoryManager
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Get a memory core block.
     *
     * @param  int  $userId  User ID to scope the query
     * @param  string  $blockKey  The block key to retrieve
     * @param  int|null  $jobId  Optional job ID for job-scoped blocks
     */
    public function get(int $userId, string $blockKey, ?int $jobId = null): ?MemoryCoreBlock
    {
        $query = MemoryCoreBlock::query()
            ->forUser($userId)
            ->where('block_key', $blockKey);

        if ($jobId !== null) {
            $query->forJob($jobId);
        } else {
            $query->global();
        }

        return $query->first();
    }

    /**
     * Set (create or update) a memory core block.
     *
     * @param  int  $userId  User ID to scope the block
     * @param  string  $blockKey  The block key to set
     * @param  mixed  $content  The content to store (text or array/object for JSON)
     * @param  int|null  $jobId  Optional job ID for job-scoped blocks
     * @param  array<string, mixed>  $options  Options:
     *                                         - 'actor' => 'user'|'agent' (default: 'user')
     *                                         - 'classification' => 'public'|'internal'|'confidential'
     *
     * @throws InvalidArgumentException When block key is invalid
     * @throws MemoryAccessDeniedException When agent tries to write identity block or downgrade classification
     */
    public function set(int $userId, string $blockKey, mixed $content, ?int $jobId = null, array $options = []): MemoryCoreBlock
    {
        $this->validateBlockKey($blockKey);

        $blockConfig = config("memory.core_blocks.{$blockKey}");
        $actor = $options['actor'] ?? 'user';
        $isAgent = $actor === 'agent';

        // Identity blocks are user-only writable
        if ($blockConfig['type'] === 'identity' && $isAgent) {
            throw new MemoryAccessDeniedException('Identity blocks cannot be modified by agents');
        }

        $existing = $this->get($userId, $blockKey, $jobId);
        $classification = $options['classification'] ?? config('memory.default_classification', MemoryCoreBlock::CLASSIFICATION_INTERNAL);

        // Validate classification change
        if ($existing !== null && isset($options['classification'])) {
            if ($isAgent && ! MemoryCoreBlock::canChangeClassification($existing->classification, $classification, true)) {
                throw new MemoryAccessDeniedException('Agents cannot downgrade classification');
            }
        }

        if ($existing !== null) {
            return $this->updateBlock($existing, $content, $classification, $userId);
        }

        return $this->createBlock($userId, $blockKey, $blockConfig, $content, $classification, $jobId);
    }

    /**
     * Delete a memory core block.
     *
     * @param  int  $userId  User ID to scope the deletion
     * @param  string  $blockKey  The block key to delete
     * @param  int|null  $jobId  Optional job ID for job-scoped blocks
     */
    public function delete(int $userId, string $blockKey, ?int $jobId = null): bool
    {
        $block = $this->get($userId, $blockKey, $jobId);

        if ($block === null) {
            return false;
        }

        $beforeState = [
            'block_key' => $block->block_key,
            'version' => $block->version,
            'classification' => $block->classification,
        ];

        $blockId = $block->id;
        $block->delete();

        $this->auditLogger->recordMemoryAction(
            userId: $userId,
            action: 'memory_core_block_deleted',
            targetType: 'memory_core_block',
            targetId: $blockId,
            changedFields: ['deleted'],
            before: $beforeState,
            after: null,
        );

        return true;
    }

    /**
     * List all memory core blocks for a user.
     *
     * @param  int  $userId  User ID to scope the query
     * @param  int|null  $jobId  Optional job ID to filter by (null = global blocks only)
     * @return Collection<int, MemoryCoreBlock>
     */
    public function listBlocks(int $userId, ?int $jobId = null): Collection
    {
        $query = MemoryCoreBlock::query()->forUser($userId);

        if ($jobId !== null) {
            $query->forJob($jobId);
        } else {
            $query->global();
        }

        return $query->get();
    }

    /**
     * Validate that a block key is allowed.
     *
     * @throws InvalidArgumentException When block key is not in config
     */
    private function validateBlockKey(string $blockKey): void
    {
        $validKeys = array_keys(config('memory.core_blocks', []));

        if (! in_array($blockKey, $validKeys, true)) {
            throw new InvalidArgumentException("Invalid block key: {$blockKey}. Valid keys are: ".implode(', ', $validKeys));
        }
    }

    /**
     * Create a new memory core block.
     *
     * @param  array<string, mixed>  $blockConfig
     */
    private function createBlock(
        int $userId,
        string $blockKey,
        array $blockConfig,
        mixed $content,
        string $classification,
        ?int $jobId
    ): MemoryCoreBlock {
        $block = new MemoryCoreBlock([
            'user_id' => $userId,
            'job_id' => $jobId,
            'block_type' => $blockConfig['type'],
            'block_key' => $blockKey,
            'classification' => $classification,
            'version' => 1,
        ]);

        $block->setContent($content);
        $block->save();

        $this->auditLogger->recordMemoryAction(
            userId: $userId,
            action: 'memory_core_block_created',
            targetType: 'memory_core_block',
            targetId: $block->id,
            changedFields: ['block_key', 'content', 'classification', 'version'],
            before: null,
            after: [
                'block_key' => $block->block_key,
                'version' => $block->version,
                'classification' => $block->classification,
            ],
        );

        return $block;
    }

    /**
     * Update an existing memory core block.
     */
    private function updateBlock(
        MemoryCoreBlock $block,
        mixed $content,
        string $classification,
        int $userId
    ): MemoryCoreBlock {
        $beforeState = [
            'block_key' => $block->block_key,
            'version' => $block->version,
            'classification' => $block->classification,
        ];

        $block->setContent($content);
        $block->classification = $classification;
        $block->incrementVersion();
        $block->save();

        $this->auditLogger->recordMemoryAction(
            userId: $userId,
            action: 'memory_core_block_updated',
            targetType: 'memory_core_block',
            targetId: $block->id,
            changedFields: ['content', 'classification', 'version'],
            before: $beforeState,
            after: [
                'block_key' => $block->block_key,
                'version' => $block->version,
                'classification' => $block->classification,
            ],
        );

        return $block;
    }
}
