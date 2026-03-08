<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connectors\ListConnectorsRequest;
use App\Http\Resources\Connectors\ConnectorDetailResource;
use App\Http\Resources\Connectors\ConnectorResource;
use App\Models\AgentConnector;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConnectorLibraryController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $flags,
    ) {}

    public function index(ListConnectorsRequest $request): AnonymousResourceCollection
    {
        $this->ensureConnectorsEnabled();

        $query = AgentConnector::query()->available();

        if ($request->filled('category')) {
            $query->byCategory($request->validated('category'));
        }

        if ($request->filled('industry')) {
            $query->whereJsonContains('industries', $request->validated('industry'));
        }

        return ConnectorResource::collection($query->orderBy('name')->get());
    }

    public function show(string $id): ConnectorDetailResource|JsonResponse
    {
        $this->ensureConnectorsEnabled();

        $connector = AgentConnector::find($id);

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

        $connector = AgentConnector::find($id);

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
