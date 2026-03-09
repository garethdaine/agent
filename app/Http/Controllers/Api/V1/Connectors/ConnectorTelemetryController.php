<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Actions\Connector\FindConnectorConnectionAction;
use App\Actions\Connector\ListInvocationsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Connectors\InvocationResource;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConnectorTelemetryController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $flags,
        private readonly FindConnectorConnectionAction $findConnectorConnection,
        private readonly ListInvocationsAction $listInvocations,
    ) {}

    public function index(Request $request, string $id): AnonymousResourceCollection
    {
        $this->ensureConnectorsEnabled();

        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $connection = $this->findConnectorConnection->findByTeamAndConnector($team->id, $id);

        Gate::authorize('manage', $connection);

        $invocations = $this->listInvocations->execute($id, $connection->id);

        return InvocationResource::collection($invocations);
    }

    private function ensureConnectorsEnabled(): void
    {
        if (! $this->flags->enabled(FeatureFlagManager::CONNECTORS_ENABLED)) {
            abort(404, 'Connectors feature is not enabled.');
        }
    }
}
