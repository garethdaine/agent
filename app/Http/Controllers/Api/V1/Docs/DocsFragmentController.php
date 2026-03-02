<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Docs;

use App\Http\Controllers\Controller;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Documentation\TooltipRegistryService;
use Illuminate\Http\JsonResponse;

class DocsFragmentController extends Controller
{
    public function __construct(
        private readonly TooltipRegistryService $registry
    ) {}

    public function show(string $uiKey): JsonResponse
    {
        $fragment = $this->registry->resolve($uiKey, context: [
            'source' => 'api',
        ]);

        if ($fragment === null) {
            return ErrorEnvelope::make(
                'NOT_FOUND',
                "Documentation fragment '{$uiKey}' not found.",
                404
            );
        }

        return response()->json([
            'data' => $fragment,
        ]);
    }
}
