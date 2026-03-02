<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Models\MemoryConversationLog;
use App\Models\MemoryCoreBlock;
use App\Models\MemoryEmbedding;
use App\Models\MemoryProviderUsage;
use App\Support\Memory\MemoryCapabilityResolver;
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
        private readonly MemoryCapabilityResolver $capabilityResolver
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

        // Provider usage statistics
        $providerUsage = MemoryProviderUsage::getUsageStats($userId);

        // Operating mode
        $operatingMode = $this->capabilityResolver->getOperatingMode($userId);

        return response()->json([
            'data' => [
                'core_blocks_count' => $coreBlocksCount,
                'embeddings_count' => $embeddingsCount,
                'conversation_logs_count' => $conversationLogsCount,
                'provider_usage' => $providerUsage,
                'operating_mode' => $operatingMode,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
