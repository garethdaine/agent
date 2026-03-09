<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Interrogation\CreateTechStackAction;
use App\Actions\Interrogation\DeleteTechStackAction;
use App\Actions\Interrogation\FindInterrogationSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\StoreInterrogationTechStackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterrogationTechStackController extends Controller
{
    public function __construct(
        private readonly FindInterrogationSessionAction $findSession,
        private readonly CreateTechStackAction $createTechStack,
        private readonly DeleteTechStackAction $deleteTechStack,
    ) {}

    public function store(StoreInterrogationTechStackRequest $request, int $id): JsonResponse
    {
        $session = $this->findSession->execute($request->user()->id, $id);
        $validated = $request->validated();

        $stack = $this->createTechStack->execute($session, $validated);

        return response()->json([
            'data' => [
                'id' => $stack->id,
                'sequence' => $stack->sequence,
                'name' => $stack->name,
                'documentation_url' => $stack->documentation_url,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id, int $stackId): JsonResponse
    {
        $session = $this->findSession->execute($request->user()->id, $id);

        $this->deleteTechStack->execute($session, $stackId);

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $stackId,
            ],
        ]);
    }
}
