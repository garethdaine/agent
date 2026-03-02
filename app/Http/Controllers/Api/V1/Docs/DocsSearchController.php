<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Docs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Docs\SearchDocsRequest;
use App\Support\Documentation\DocsCatalog;
use Illuminate\Http\JsonResponse;

class DocsSearchController extends Controller
{
    public function __construct(
        private readonly DocsCatalog $catalog
    ) {}

    public function index(SearchDocsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->catalog->search(
            $validated['q'] ?? null,
            $validated['domain'] ?? null,
            $validated['section'] ?? null,
            (int) ($validated['limit'] ?? 20)
        );

        return response()->json([
            'data' => $results,
            'meta' => [
                'count' => count($results),
            ],
        ]);
    }
}
