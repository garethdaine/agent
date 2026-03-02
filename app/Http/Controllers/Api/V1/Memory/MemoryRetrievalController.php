<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memory\RetrieveMemoryRequest;
use App\Support\Memory\HybridRetriever;
use Illuminate\Http\JsonResponse;

/**
 * Memory retrieval API controller.
 *
 * Endpoints:
 * - POST /memory/retrieve - Hybrid retrieval query
 */
class MemoryRetrievalController extends Controller
{
    public function __construct(
        private readonly HybridRetriever $hybridRetriever
    ) {}

    /**
     * POST /memory/retrieve
     *
     * Execute hybrid retrieval across semantic, keyword, and graph sources.
     */
    public function retrieve(RetrieveMemoryRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = $request->input('query');
        $options = $request->getRetrievalOptions();

        $results = $this->hybridRetriever->retrieve($userId, $query, $options);

        // Determine which sources were used based on result sources
        $sourcesUsed = collect($results)
            ->pluck('source')
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'results' => $results,
                'sources_used' => $sourcesUsed,
                'query' => $query,
            ],
        ]);
    }
}
