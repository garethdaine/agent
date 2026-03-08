<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Http\Controllers\Controller;
use App\Http\Resources\Connectors\InvocationResource;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ConnectorTelemetryController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $flags,
    ) {}

    public function index(Request $request, string $id): AnonymousResourceCollection
    {
        $this->ensureConnectorsEnabled();

        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $id)
            ->firstOrFail();

        Gate::authorize('manage', $connection);

        $invocations = AgentConnectorInvocation::where('connector_id', $id)
            ->where('connection_id', $connection->id)
            ->orderByDesc('created_at')
            ->get();

        return InvocationResource::collection($invocations);
    }

    private function ensureConnectorsEnabled(): void
    {
        if (! $this->flags->enabled(FeatureFlagManager::CONNECTORS_ENABLED)) {
            abort(404, 'Connectors feature is not enabled.');
        }
    }
}
