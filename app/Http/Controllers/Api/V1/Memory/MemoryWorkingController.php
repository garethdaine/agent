<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memory\AppendWorkingMemoryRequest;
use App\Models\AgentJobRun;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Memory\WorkingMemoryBuffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Working memory API controller.
 *
 * Endpoints:
 * - POST /memory/working/append - Append to working memory
 * - GET /memory/working/{runId} - Read working memory for run
 */
class MemoryWorkingController extends Controller
{
    public function __construct(
        private readonly WorkingMemoryBuffer $workingMemoryBuffer
    ) {}

    /**
     * POST /memory/working/append
     *
     * Append an entry to the working memory buffer.
     */
    public function append(AppendWorkingMemoryRequest $request): JsonResponse
    {
        $runId = $request->integer('run_id');
        $role = $request->input('role');
        $content = $request->input('content');
        $metadata = $request->getMetadata();

        $this->workingMemoryBuffer->append($runId, $role, $content, $metadata);

        return response()->json([
            'data' => [
                'appended' => true,
                'run_id' => $runId,
            ],
        ]);
    }

    /**
     * GET /memory/working/{runId}
     *
     * Retrieve the working memory buffer for a run.
     */
    public function show(Request $request, int $runId): JsonResponse
    {
        $userId = $request->user()->id;

        // Validate the run belongs to the user
        $run = AgentJobRun::with('job')->find($runId);

        if (! $run || ! $run->job || $run->job->user_id !== $userId) {
            return ErrorEnvelope::make(
                'FORBIDDEN',
                'You do not have access to this run.',
                403
            );
        }

        $entries = $this->workingMemoryBuffer->getRecent($runId);

        return response()->json([
            'data' => [
                'run_id' => $runId,
                'entries' => $entries,
                'count' => count($entries),
            ],
        ]);
    }
}
