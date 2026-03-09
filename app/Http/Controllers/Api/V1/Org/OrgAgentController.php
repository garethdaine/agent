<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Actions\Organization\FindOrgAgentProfileAction;
use App\Actions\Organization\ListOrgAgentProfilesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Org\StoreOrgAgentRequest;
use App\Http\Requests\Org\UpdateOrgAgentRequest;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Org\OrgAgentProfileService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrgAgentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OrgAgentProfileService $service,
        private readonly ListOrgAgentProfilesAction $listProfiles,
        private readonly FindOrgAgentProfileAction $findProfile,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->agentReadTablesExist()) {
            return response()->json([
                'data' => [],
            ]);
        }

        try {
            $with = ['delegateeProfile'];
            if ($request->boolean('with_reporting')) {
                $with[] = 'reportingEdge';
            }

            return response()->json([
                'data' => $this->listProfiles->execute(
                    $request->user()->id,
                    $request->boolean('include_archived'),
                    $with,
                ),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'data' => [],
            ]);
        }
    }

    public function store(StoreOrgAgentRequest $request): JsonResponse
    {
        $profile = $this->service->create(
            $request->user(),
            $request->validated()
        );

        return response()->json(['data' => $profile->load('delegateeProfile')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $profile = $this->findProfile->execute($id);

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $this->authorize('view', $profile);

        return response()->json([
            'data' => $profile->load(['delegateeProfile', 'reportingEdge.manager']),
        ]);
    }

    public function update(UpdateOrgAgentRequest $request, string $id): JsonResponse
    {
        $profile = $this->findProfile->execute($id);

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $this->authorize('update', $profile);

        $profile = $this->service->update($profile, $request->validated());

        return response()->json(['data' => $profile]);
    }

    public function destroy(string $id): JsonResponse
    {
        $profile = $this->findProfile->execute($id);

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $this->authorize('delete', $profile);

        $this->service->archive($profile);

        return response()->json(['message' => 'Profile archived']);
    }

    public function restore(string $id): JsonResponse
    {
        $profile = $this->findProfile->execute($id);

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $this->authorize('restore', $profile);

        $this->service->restore($profile);

        return response()->json(['data' => $profile->fresh()]);
    }

    private function agentReadTablesExist(): bool
    {
        return Schema::hasTable('org_agent_profiles')
            && Schema::hasTable('delegatee_profiles');
    }
}
