<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Actions\Connector\CountDeadLettersAction;
use App\Actions\Connector\GetRecentErrorRateAction;
use App\Actions\Connector\ListAllConnectorAccountsAction;
use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use App\Support\Messenger\MetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;

class MessengerHealthController extends Controller
{
    public function __construct(
        private readonly MetricsCollector $metricsCollector, // @phpstan-ignore property.onlyWritten
        private readonly ListAllConnectorAccountsAction $listAllConnectorAccounts,
        private readonly CountDeadLettersAction $countDeadLetters,
        private readonly GetRecentErrorRateAction $getRecentErrorRate,
    ) {}

    public function index(): JsonResponse
    {
        $connectors = $this->listAllConnectorAccounts->execute(['id', 'provider', 'name', 'status', 'connection_mode', 'runtime_state']);

        $status = $this->determineOverallStatus($connectors);
        $queueBacklog = $this->getQueueBacklogSize();
        $recentErrorRate = $this->getRecentErrorRate->execute();
        $deadLetterCount = $this->countDeadLetters->execute();

        return response()->json([
            'status' => $status,
            'connectors' => $connectors->map(fn ($connector) => [
                'id' => $connector->id,
                'provider' => $connector->provider,
                'name' => $connector->name,
                'status' => $connector->status,
                'runtime_state' => $connector->runtime_state->value,
                'effective_state' => $this->effectiveConnectorState($connector),
            ])->values(),
            'queue' => [
                'backlog_size' => $queueBacklog,
            ],
            'recent_error_rate' => $recentErrorRate,
            'dead_letter_count' => $deadLetterCount,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ConnectorAccount>  $connectors
     */
    private function determineOverallStatus($connectors): string
    {
        if ($connectors->isEmpty()) {
            return 'unknown';
        }

        $states = $connectors
            ->map(fn (ConnectorAccount $connector) => $this->effectiveConnectorState($connector))
            ->values();

        $hasError = $states->contains(ConnectorAccount::STATUS_ERROR);
        $allConnected = $states->every(fn (string $state) => $state === ConnectorAccount::STATUS_CONNECTED);

        if ($hasError) {
            return 'degraded';
        }

        if ($allConnected) {
            return 'healthy';
        }

        return 'degraded';
    }

    private function effectiveConnectorState(ConnectorAccount $connector): string
    {
        if ($connector->isLocalMode()) {
            $runtimeState = $connector->runtime_state->value;
            if (is_string($runtimeState) && trim($runtimeState) !== '') { // @phpstan-ignore function.alreadyNarrowedType
                return $runtimeState;
            }
        }

        return (string) $connector->status;
    }

    private function getQueueBacklogSize(): int
    {
        try {
            return Queue::size('messenger');
        } catch (\Throwable) {
            return 0;
        }
    }
}
