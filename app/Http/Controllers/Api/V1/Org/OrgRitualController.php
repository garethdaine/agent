<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Actions\Organization\FindOrgRitualTemplateAction;
use App\Actions\Organization\ListOrgRitualTemplatesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\StoreOrgRitualRequest;
use App\Http\Requests\Org\UpdateOrgRitualRequest;
use App\Jobs\Org\OrgExecuteRitualJob;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Org\OrgRitualRunService;
use App\Support\Org\OrgRitualTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrgRitualController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OrgRitualTemplateService $templateService,
        private readonly OrgRitualRunService $runService,
        private readonly ListOrgRitualTemplatesAction $listTemplates,
        private readonly FindOrgRitualTemplateAction $findTemplate,
    ) {}

    /**
     * List user's ritual templates.
     */
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('org_ritual_templates')) {
            return response()->json([
                'data' => [],
            ]);
        }

        try {
            return response()->json([
                'data' => $this->listTemplates->execute(
                    $request->user()->id,
                    $request->boolean('include_archived'),
                ),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'data' => [],
            ]);
        }
    }

    /**
     * Create a new ritual template.
     */
    public function store(StoreOrgRitualRequest $request): JsonResponse
    {
        $template = $this->templateService->create(
            $request->user(),
            $request->validated()
        );

        return response()->json(['data' => $template], 201);
    }

    /**
     * Show a ritual template.
     */
    public function show(string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('view', $template);

        return response()->json([
            'data' => $template->load('runs'),
        ]);
    }

    /**
     * Update a ritual template.
     */
    public function update(UpdateOrgRitualRequest $request, string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('update', $template);

        $template = $this->templateService->update($template, $request->validated());

        return response()->json(['data' => $template]);
    }

    /**
     * Archive (soft delete) a ritual template.
     */
    public function destroy(string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('delete', $template);

        $this->templateService->archive($template);

        return response()->json(['message' => 'Ritual template archived']);
    }

    /**
     * Restore an archived ritual template.
     */
    public function restore(string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('restore', $template);

        $this->templateService->restore($template);

        return response()->json(['data' => $template->fresh()]);
    }

    /**
     * Trigger an immediate run of a ritual.
     */
    public function run(string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('run', $template);

        $run = $this->runService->createRun($template);

        OrgExecuteRitualJob::dispatch($template, $run)->onQueue('org-rituals');

        return response()->json(['data' => $run], 201);
    }

    /**
     * Pause a ritual template's schedule.
     */
    public function pause(string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('update', $template);

        $this->templateService->pause($template);

        return response()->json(['data' => $template->fresh()]);
    }

    /**
     * Resume a paused ritual template's schedule.
     */
    public function resume(string $id): JsonResponse
    {
        $template = $this->findTemplate->execute($id);

        if ($template === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Ritual template not found.', 404);
        }

        $this->authorize('update', $template);

        $this->templateService->resume($template);

        return response()->json(['data' => $template->fresh()]);
    }
}
