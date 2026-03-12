<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Actions\Connector\FindAgentConnectorAction;
use App\Actions\Connector\ListAvailableConnectorsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Connectors\ListConnectorsRequest;
use App\Http\Resources\Connectors\ConnectorDetailResource;
use App\Http\Resources\Connectors\ConnectorResource;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConnectorLibraryController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $flags,
        private readonly ListAvailableConnectorsAction $listAvailableConnectors,
        private readonly FindAgentConnectorAction $findAgentConnector,
    ) {}

    public function index(ListConnectorsRequest $request): AnonymousResourceCollection
    {
        $this->ensureConnectorsEnabled();

        $team = $request->user()->currentTeam;

        $connectors = $this->listAvailableConnectors->execute([
            'category' => $request->filled('category') ? $request->validated('category') : null,
            'industry' => $request->filled('industry') ? $request->validated('industry') : null,
            'status' => $request->validated('status'),
            'team_id' => $team?->id !== null ? (string) $team->id : null,
        ]);

        return ConnectorResource::collection($connectors);
    }

    public function show(string $id): ConnectorDetailResource|JsonResponse
    {
        $this->ensureConnectorsEnabled();

        $connector = $this->findAgentConnector->find($id);

        if (! $connector) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Connector not found.',
                ],
            ], 404);
        }

        return new ConnectorDetailResource($connector);
    }

    public function actions(string $id): JsonResponse
    {
        $this->ensureConnectorsEnabled();

        $connector = $this->findAgentConnector->find($id);

        if (! $connector) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Connector not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $connector->actions ?? [],
        ]);
    }

    private function ensureConnectorsEnabled(): void
    {
        if (! $this->flags->enabled(FeatureFlagManager::CONNECTORS_ENABLED)) {
            abort(404, 'Connectors feature is not enabled.');
        }
    }
}
