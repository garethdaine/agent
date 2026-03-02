<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memory\UpdateCoreBlockRequest;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Memory\CoreMemoryManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Core memory blocks API controller.
 *
 * Endpoints:
 * - GET /memory/core-blocks - List blocks for authenticated user
 * - GET /memory/core-blocks/{key} - Read single block
 * - PUT /memory/core-blocks/{key} - Create/update block
 * - DELETE /memory/core-blocks/{key} - Delete block
 */
class MemoryCoreBlockController extends Controller
{
    public function __construct(
        private readonly CoreMemoryManager $coreMemoryManager
    ) {}

    /**
     * GET /memory/core-blocks
     *
     * List all core blocks for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $jobId = $request->integer('job_id') ?: null;

        $blocks = $this->coreMemoryManager->listBlocks($userId, $jobId);

        return response()->json([
            'data' => $blocks->map(fn ($block) => $this->transformBlock($block))->values(),
        ]);
    }

    /**
     * GET /memory/core-blocks/{key}
     *
     * Retrieve a single core block.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $userId = $request->user()->id;
        $jobId = $request->integer('job_id') ?: null;

        $block = $this->coreMemoryManager->get($userId, $key, $jobId);

        if (! $block) {
            return ErrorEnvelope::make(
                'NOT_FOUND',
                "Core block '{$key}' not found.",
                404
            );
        }

        return response()->json([
            'data' => $this->transformBlock($block),
        ]);
    }

    /**
     * PUT /memory/core-blocks/{key}
     *
     * Create or update a core block.
     */
    public function update(UpdateCoreBlockRequest $request, string $key): JsonResponse
    {
        $userId = $request->user()->id;
        $jobId = $request->integer('job_id') ?: null;

        $options = [];
        if ($request->has('classification')) {
            $options['classification'] = $request->input('classification');
        }

        $block = $this->coreMemoryManager->set(
            $userId,
            $key,
            $request->input('content'),
            $jobId,
            $options
        );

        return response()->json([
            'data' => $this->transformBlock($block),
        ]);
    }

    /**
     * DELETE /memory/core-blocks/{key}
     *
     * Delete a core block.
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $userId = $request->user()->id;
        $jobId = $request->integer('job_id') ?: null;

        $deleted = $this->coreMemoryManager->delete($userId, $key, $jobId);

        if (! $deleted) {
            return ErrorEnvelope::make(
                'NOT_FOUND',
                "Core block '{$key}' not found.",
                404
            );
        }

        return response()->json([
            'data' => [
                'deleted' => true,
                'block_key' => $key,
            ],
        ]);
    }

    /**
     * Transform a core block for API response.
     *
     * @param  \App\Models\MemoryCoreBlock  $block
     * @return array<string, mixed>
     */
    private function transformBlock($block): array
    {
        return [
            'id' => $block->id,
            'block_key' => $block->block_key,
            'block_type' => $block->block_type,
            'content_text' => $block->content_text,
            'content_json' => $block->content_json,
            'classification' => $block->classification,
            'version' => $block->version,
            'job_id' => $block->job_id,
            'created_at' => $block->created_at?->toIso8601String(),
            'updated_at' => $block->updated_at?->toIso8601String(),
        ];
    }
}
