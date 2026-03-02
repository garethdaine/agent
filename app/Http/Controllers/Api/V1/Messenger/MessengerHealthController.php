<?php

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Http\Controllers\Controller;
use App\Models\ChatAction;
use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use App\Support\Messenger\MetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;

class MessengerHealthController extends Controller
{
    public function __construct(
        private readonly MetricsCollector $metricsCollector
    ) {}

    public function index(): JsonResponse
    {
        $connectors = ConnectorAccount::query()
            ->select('id', 'provider', 'name', 'status', 'connection_mode', 'runtime_state')
            ->get();

        $status = $this->determineOverallStatus($connectors);
        $queueBacklog = $this->getQueueBacklogSize();
        $recentErrorRate = $this->calculateRecentErrorRate();
        $deadLetterCount = $this->getDeadLetterCount();

        return response()->json([
            'status' => $status,
            'connectors' => $connectors->map(fn ($connector) => [
                'id' => $connector->id,
                'provider' => $connector->provider,
                'name' => $connector->name,
                'status' => $connector->status,
                'runtime_state' => $connector->runtime_state?->value,
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
            $runtimeState = $connector->runtime_state?->value;
            if (is_string($runtimeState) && trim($runtimeState) !== '') {
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

    private function calculateRecentErrorRate(): float
    {
        $recentActions = ChatAction::query()
            ->where('created_at', '>=', now()->subHour())
            ->whereIn('status', [ChatAction::STATUS_COMPLETED, ChatAction::STATUS_FAILED])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $completed = $recentActions->get(ChatAction::STATUS_COMPLETED, 0);
        $failed = $recentActions->get(ChatAction::STATUS_FAILED, 0);
        $total = $completed + $failed;

        if ($total === 0) {
            return 0.0;
        }

        return round(($failed / $total) * 100, 2);
    }

    private function getDeadLetterCount(): int
    {
        return MessengerDeadLetter::count();
    }
}
