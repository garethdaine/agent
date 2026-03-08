<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Models\MemoryConversationLog;
use App\Models\MemoryCoreBlock;
use App\Models\MemoryEmbedding;
use App\Models\MemoryProviderUsage;
use App\Support\Memory\MemoryCapabilityResolver;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Memory diagnostics API controller.
 *
 * Endpoints:
 * - GET /memory/stats - Per-layer diagnostics + provider usage
 */
class MemoryDiagnosticsController extends Controller
{
    public function __construct(
        private readonly MemoryCapabilityResolver $capabilityResolver,
        private readonly Neo4jGraphStore $graphStore
    ) {}

    /**
     * GET /memory/stats
     *
     * Returns diagnostics across all memory layers.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Core blocks statistics
        $coreBlocksCount = MemoryCoreBlock::forUser($userId)->count();

        // Embeddings statistics
        $embeddingsCount = MemoryEmbedding::forUser($userId)->count();

        // Conversation logs statistics
        $conversationLogsCount = MemoryConversationLog::forUser($userId)->count();

        // Graph entities (graceful fallback if Neo4j unavailable)
        $graphEntitiesCount = $this->graphStore->getEntityCount($userId);

        // Provider usage statistics (memory API operations only)
        $providerUsage = MemoryProviderUsage::getUsageStats(
            $userId,
            excludeOperation: MemoryProviderUsage::OPERATION_CLI_EXECUTION
        );

        // CLI execution usage (agent run token consumption)
        $cliUsage = MemoryProviderUsage::getUsageStats(
            $userId,
            onlyOperation: MemoryProviderUsage::OPERATION_CLI_EXECUTION
        );

        // Operating mode
        $operatingMode = $this->capabilityResolver->getOperatingMode($userId);

        return response()->json([
            'data' => [
                'core_blocks_count' => $coreBlocksCount,
                'embeddings_count' => $embeddingsCount,
                'conversation_logs_count' => $conversationLogsCount,
                'graph_entities_count' => $graphEntitiesCount,
                'provider_usage' => $providerUsage,
                'cli_usage' => $cliUsage,
                'operating_mode' => $operatingMode,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
