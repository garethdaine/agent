<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Docs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docs\SearchDocsRequest;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Documentation\DocsSearchService;
use App\Support\Documentation\DocsSearchUnavailableException;
use App\Support\Documentation\DocsTelemetryService;
use Illuminate\Http\JsonResponse;

class DocsSearchController extends Controller
{
    public function __construct(
        private readonly DocsSearchService $searchService,
        private readonly DocsTelemetryService $telemetry,
    ) {}

    public function index(SearchDocsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $results = $this->searchService->search(
                (string) $validated['q'],
                $validated['domain'] ?? null,
                $validated['section'] ?? null,
                $validated['route'] ?? null,
                (int) ($validated['limit'] ?? 20)
            );
        } catch (DocsSearchUnavailableException $exception) {
            $this->telemetry->recordSearchUnavailable(
                query: (string) ($validated['q'] ?? ''),
                routeName: isset($validated['route']) ? (string) $validated['route'] : null,
                throwable: $exception->getPrevious() ?? $exception,
                context: [
                    'source' => 'api',
                ],
            );

            return ErrorEnvelope::make(
                'SEARCH_UNAVAILABLE',
                'search temporarily unavailable',
                503
            );
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'count' => count($results),
            ],
        ]);
    }
}
