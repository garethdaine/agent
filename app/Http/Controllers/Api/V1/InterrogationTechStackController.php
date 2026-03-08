<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interrogation\StoreInterrogationTechStackRequest;
use App\Models\InterrogationSession;
use App\Models\InterrogationTechStack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterrogationTechStackController extends Controller
{
    public function store(StoreInterrogationTechStackRequest $request, int $id): JsonResponse
    {
        /** @var InterrogationSession $session */
        $session = $request->user()->interrogationSessions()->findOrFail($id);
        $validated = $request->validated();

        $maxSequence = (int) $session->techStacks()->max('sequence');
        /** @var InterrogationTechStack $stack */
        $stack = $session->techStacks()->create([
            'sequence' => $maxSequence + 1,
            'name' => $validated['name'],
            'documentation_url' => $validated['documentation_url'],
            'metadata_json' => [
                'source' => 'manual',
            ],
        ]);

        if ((int) $session->phase <= InterrogationSession::PHASE_PROVIDER_SETUP) {
            $session->phase = InterrogationSession::PHASE_TECH_STACK_SETUP;
            $session->status = InterrogationSession::STATUS_SETUP;
            $session->save();
        }

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
        /** @var InterrogationSession $session */
        $session = $request->user()->interrogationSessions()->findOrFail($id);

        $stack = InterrogationTechStack::query()
            ->where('interrogation_session_id', $session->id)
            ->whereKey($stackId)
            ->firstOrFail();

        $stack->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $stackId,
            ],
        ]);
    }
}
