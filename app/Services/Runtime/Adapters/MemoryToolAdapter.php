<?php

declare(strict_types=1);

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Models\MemoryConversationLog;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\CoreMemoryManager;
use App\Support\Memory\HybridRetriever;
use App\Support\Memory\Neo4jGraphStore;

/**
 * Exposes the 4-layer memory system as runtime tools so agents can read/write
 * memory on the fly during execution.
 *
 * Operations:
 *  - search_memories: Hybrid search across all memory layers (RRF fusion)
 *  - get_core_block: Read a specific core memory block by key
 *  - set_core_block: Write to an operational core memory block
 *  - list_core_blocks: List all core memory blocks for the user
 *  - get_entities: Query the knowledge graph for related entities
 *  - recall_conversations: Full-text search over conversation logs
 *
 * All operations are guarded by the MEMORY_ENABLED feature flag.
 * Identity block writes (agent_persona, user_profile) are rejected —
 * enforced by CoreMemoryManager.
 */
class MemoryToolAdapter extends AbstractToolAdapter
{
    public function __construct(
        private readonly CoreMemoryManager $coreMemoryManager,
        private readonly HybridRetriever $hybridRetriever,
        private readonly Neo4jGraphStore $graphStore,
        private readonly FeatureFlagManager $featureFlags,
    ) {}

    public function name(): string
    {
        return 'memory';
    }

    public function schema(): array
    {
        return [
            'description' => 'Persistent memory system — search, read, and write to your 4-layer memory architecture. '
                .'Operations: search_memories (hybrid search across all memory layers), '
                .'get_core_block (read a specific core block by key), '
                .'set_core_block (write to an operational core block — task_state, active_goals, tool_results_cache), '
                .'list_core_blocks (list all core blocks), '
                .'get_entities (query the knowledge graph for related entities), '
                .'recall_conversations (full-text search over past conversation logs). '
                .'Use memory proactively to maintain context across conversations and store important task state.',
            'operations' => ['search_memories', 'get_core_block', 'set_core_block', 'list_core_blocks', 'get_entities', 'recall_conversations'],
            'parameters' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'The memory operation to perform.',
                    'required' => true,
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query for search_memories, get_entities, or recall_conversations.',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Core block key for get_core_block or set_core_block (e.g., task_state, active_goals, tool_results_cache, agent_persona, user_profile).',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Content to write for set_core_block.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results to return (default: 10, max: 50).',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Entity type filter for get_entities (e.g., person, project, technology, file).',
                ],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return false;
        }

        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        $operation = $args['operation'] ?? '';

        if ($operation === 'set_core_block') {
            return 'memory_write';
        }

        return 'memory_read';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);

        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return ToolResult::failure(
                'Memory system is disabled. Enable via memory.enabled setting.',
                $this->duration($startTime),
            );
        }

        $operation = $args['operation'] ?? '';

        try {
            return match ($operation) {
                'search_memories' => $this->searchMemories($context, $args, $startTime),
                'get_core_block' => $this->getCoreBlock($context, $args, $startTime),
                'set_core_block' => $this->setCoreBlock($context, $args, $startTime),
                'list_core_blocks' => $this->listCoreBlocks($context, $startTime),
                'get_entities' => $this->getEntities($context, $args, $startTime),
                'recall_conversations' => $this->recallConversations($context, $args, $startTime),
                default => ToolResult::failure(
                    "Unknown memory operation: {$operation}. Use: search_memories, get_core_block, set_core_block, list_core_blocks, get_entities, recall_conversations",
                    $this->duration($startTime),
                ),
            };
        } catch (\Throwable $e) {
            return ToolResult::failure(
                "Memory operation failed: {$e->getMessage()}",
                $this->duration($startTime),
            );
        }
    }

    /**
     * Hybrid search across all memory layers using RRF fusion.
     */
    private function searchMemories(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $query = $args['query'] ?? '';
        if ($query === '') {
            return ToolResult::failure('query parameter is required for search_memories', $this->duration($startTime));
        }

        $limit = min((int) ($args['limit'] ?? 10), 50);

        $results = $this->hybridRetriever->retrieve(
            $context->user->id,
            $query,
            ['limit' => $limit],
        );

        return ToolResult::success([
            'results' => $results,
            'count' => count($results),
            'query' => $query,
        ], $this->duration($startTime));
    }

    /**
     * Read a specific core memory block by key.
     */
    private function getCoreBlock(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $key = $args['key'] ?? '';
        if ($key === '') {
            return ToolResult::failure('key parameter is required for get_core_block', $this->duration($startTime));
        }

        $block = $this->coreMemoryManager->get($context->user->id, $key);

        if ($block === null) {
            return ToolResult::success([
                'key' => $key,
                'exists' => false,
                'content' => null,
            ], $this->duration($startTime));
        }

        return ToolResult::success([
            'key' => $block->block_key,
            'exists' => true,
            'content' => $block->content_text,
            'classification' => $block->classification,
            'version' => $block->version,
            'updated_at' => $block->updated_at?->toIso8601String(),
        ], $this->duration($startTime));
    }

    /**
     * Write to an operational core memory block.
     *
     * Identity blocks (agent_persona, user_profile) are rejected by CoreMemoryManager
     * when the actor is 'agent'.
     */
    private function setCoreBlock(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $key = $args['key'] ?? '';
        if ($key === '') {
            return ToolResult::failure('key parameter is required for set_core_block', $this->duration($startTime));
        }

        $content = $args['content'] ?? '';
        if ($content === '') {
            return ToolResult::failure('content parameter is required for set_core_block', $this->duration($startTime));
        }

        try {
            $block = $this->coreMemoryManager->set(
                $context->user->id,
                $key,
                $content,
                options: ['actor' => 'agent'],
            );

            return ToolResult::success([
                'key' => $block->block_key,
                'version' => $block->version,
                'updated_at' => $block->updated_at?->toIso8601String(),
                'message' => "Core block '{$key}' updated successfully",
            ], $this->duration($startTime));
        } catch (\App\Support\Memory\Exceptions\MemoryAccessDeniedException $e) {
            return ToolResult::failure(
                "Cannot write to core block '{$key}': {$e->getMessage()}",
                $this->duration($startTime),
            );
        }
    }

    /**
     * List all core memory blocks for the user.
     */
    private function listCoreBlocks(RuntimeContext $context, int $startTime): ToolResult
    {
        $blocks = $this->coreMemoryManager->listBlocks($context->user->id);

        $formatted = $blocks->map(fn ($block) => [
            'key' => $block->block_key,
            'content_preview' => mb_substr($block->content_text ?? '', 0, 200),
            'classification' => $block->classification,
            'version' => $block->version,
            'updated_at' => $block->updated_at?->toIso8601String(),
        ])->toArray();

        return ToolResult::success([
            'blocks' => $formatted,
            'count' => count($formatted),
        ], $this->duration($startTime));
    }

    /**
     * Query the knowledge graph for entities related to a query.
     */
    private function getEntities(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $query = $args['query'] ?? '';
        if ($query === '') {
            return ToolResult::failure('query parameter is required for get_entities', $this->duration($startTime));
        }

        if (! $this->graphStore->healthCheck()) {
            return ToolResult::success([
                'results' => [],
                'count' => 0,
                'message' => 'Knowledge graph is not available. Entity search requires Neo4j.',
            ], $this->duration($startTime));
        }

        $results = $this->graphStore->queryRelated(
            $context->user->id,
            $query,
            depth: 2,
        );

        // Filter by type if specified
        $type = $args['type'] ?? null;
        if ($type !== null && $type !== '') {
            $results = array_values(array_filter(
                $results,
                fn ($entity) => ($entity['type'] ?? '') === $type,
            ));
        }

        $limit = min((int) ($args['limit'] ?? 10), 50);
        $results = array_slice($results, 0, $limit);

        return ToolResult::success([
            'entities' => $results,
            'count' => count($results),
            'query' => $query,
        ], $this->duration($startTime));
    }

    /**
     * Full-text search over conversation logs.
     */
    private function recallConversations(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $query = $args['query'] ?? '';
        if ($query === '') {
            return ToolResult::failure('query parameter is required for recall_conversations', $this->duration($startTime));
        }

        $limit = min((int) ($args['limit'] ?? 10), 50);

        $logs = MemoryConversationLog::query()
            ->forUser($context->user->id)
            ->where('content', 'ILIKE', '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%')
            ->withRole(MemoryConversationLog::ROLE_ASSISTANT)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'role', 'content', 'event_type', 'classification', 'created_at']);

        $formatted = $logs->map(fn ($log) => [
            'id' => $log->id,
            'role' => $log->role,
            'content_preview' => mb_substr($log->content, 0, 500),
            'event_type' => $log->event_type,
            'classification' => $log->classification,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->toArray();

        return ToolResult::success([
            'conversations' => $formatted,
            'count' => count($formatted),
            'query' => $query,
        ], $this->duration($startTime));
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
