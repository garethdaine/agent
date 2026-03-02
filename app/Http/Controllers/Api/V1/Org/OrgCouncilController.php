<?php

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Http\Requests\Org\StoreOrgCouncilRequest;
use App\Http\Requests\Org\UpdateOrgCouncilRequest;
use App\Models\OrgCouncilTemplate;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Org\OrgCouncilService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrgCouncilController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OrgCouncilService $councilService,
    ) {}

    /**
     * List user's council templates.
     */
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('org_council_templates')) {
            return response()->json([
                'data' => [],
            ]);
        }

        try {
            $query = OrgCouncilTemplate::forUser($request->user()->id);

            if (! $request->boolean('include_archived')) {
                $query->active();
            }

            return response()->json([
                'data' => $query->get(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'data' => [],
            ]);
        }
    }

    /**
     * Create a new council template.
     */
    public function store(StoreOrgCouncilRequest $request): JsonResponse
    {
        try {
            $template = $this->councilService->create(
                $request->user(),
                $request->validated()
            );

            return response()->json(['data' => $template], 201);
        } catch (\InvalidArgumentException $e) {
            return ErrorEnvelope::make('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    /**
     * Show a council template.
     */
    public function show(string $id): JsonResponse
    {
        $template = OrgCouncilTemplate::find($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Council template not found.', 404);
        }

        $this->authorize('view', $template);

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Update a council template.
     */
    public function update(UpdateOrgCouncilRequest $request, string $id): JsonResponse
    {
        $template = OrgCouncilTemplate::find($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Council template not found.', 404);
        }

        $this->authorize('update', $template);

        try {
            $template = $this->councilService->update($template, $request->validated());

            return response()->json(['data' => $template]);
        } catch (\InvalidArgumentException $e) {
            return ErrorEnvelope::make('VALIDATION_ERROR', $e->getMessage(), 422);
        }
    }

    /**
     * Archive (soft delete) a council template.
     */
    public function destroy(string $id): JsonResponse
    {
        $template = OrgCouncilTemplate::find($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Council template not found.', 404);
        }

        $this->authorize('delete', $template);

        $this->councilService->archive($template);

        return response()->json(['message' => 'Council template archived']);
    }
}
