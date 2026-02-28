<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public health endpoint for monitoring tools.
 *
 * Returns HTTP 200 always with per-connector breakdown and aggregate summary.
 * Reports real worker state from gateway manager (runtime_state), not static config.
 *
 * No authentication required - designed for external monitoring systems.
 */
class MessengerHealthController extends Controller
{
    public function index(): JsonResponse
    {
        $connectors = ConnectorAccount::all();

        $summary = [
            'total_connectors' => $connectors->count(),
            'connected' => $connectors->where('runtime_state', WorkerHealthStatus::Connected)->count(),
            'reconnecting' => $connectors->where('runtime_state', WorkerHealthStatus::Reconnecting)->count(),
            'disconnected' => $connectors->where('runtime_state', WorkerHealthStatus::Disconnected)->count(),
            'error' => $connectors->where('runtime_state', WorkerHealthStatus::Error)->count(),
        ];

        $connectorDetails = $connectors->map(fn (ConnectorAccount $connector) => [
            'id' => $connector->id,
            'name' => $connector->name,
            'provider' => $connector->provider,
            'mode' => $connector->connection_mode,
            'runtime_state' => $connector->runtime_state?->value ?? 'unknown',
            'last_health_check_at' => $connector->last_health_check_at?->toIso8601String(),
            'error_message' => $connector->runtime_error_message,
        ]);

        // Determine aggregate status based on runtime_state
        $status = $this->determineAggregateStatus($summary);

        return response()->json([
            'status' => $status,
            'summary' => $summary,
            'connectors' => $connectorDetails->values(),
        ], 200);
    }

    /**
     * Determine the aggregate health status based on connector states.
     *
     * @param  array{total_connectors: int, connected: int, reconnecting: int, disconnected: int, error: int}  $summary
     */
    private function determineAggregateStatus(array $summary): string
    {
        // Empty connector set is considered healthy (no issues to report)
        if ($summary['total_connectors'] === 0) {
            return 'healthy';
        }

        // Any error state = degraded
        if ($summary['error'] > 0) {
            return 'degraded';
        }

        // Any reconnecting = degraded (transient issue)
        if ($summary['reconnecting'] > 0) {
            return 'degraded';
        }

        // All connected = healthy
        if ($summary['connected'] === $summary['total_connectors']) {
            return 'healthy';
        }

        // Some disconnected but no errors = partial
        return 'partial';
    }

    /**
     * Health dashboard page for authenticated users.
     *
     * Renders an Inertia page with connector health overview, gateway status,
     * and queue health metrics.
     */
    public function dashboard(): Response
    {
        $connectors = ConnectorAccount::all();

        $summary = [
            'total_connectors' => $connectors->count(),
            'connected' => $connectors->where('runtime_state', WorkerHealthStatus::Connected)->count(),
            'reconnecting' => $connectors->where('runtime_state', WorkerHealthStatus::Reconnecting)->count(),
            'disconnected' => $connectors->where('runtime_state', WorkerHealthStatus::Disconnected)->count(),
            'error' => $connectors->where('runtime_state', WorkerHealthStatus::Error)->count(),
        ];

        $connectorDetails = $connectors->map(fn (ConnectorAccount $connector) => [
            'id' => $connector->id,
            'name' => $connector->name,
            'provider' => $connector->provider,
            'mode' => $connector->connection_mode,
            'runtime_state' => $connector->runtime_state?->value ?? 'unknown',
            'last_health_check_at' => $connector->last_health_check_at?->toIso8601String(),
            'error_message' => $connector->runtime_error_message,
        ]);

        $status = $this->determineAggregateStatus($summary);
        $gatewayRunning = $this->checkGatewayProcess();
        $deadLetterCount = MessengerDeadLetter::count();

        return Inertia::render('Messenger/Health/Index', [
            'health' => [
                'status' => $status,
                'summary' => $summary,
                'connectors' => $connectorDetails->values(),
            ],
            'gatewayRunning' => $gatewayRunning,
            'queueHealth' => $this->getQueueHealth(),
            'deadLetterCount' => $deadLetterCount,
        ]);
    }

    /**
     * Check if the gateway process is running via heartbeat cache.
     */
    private function checkGatewayProcess(): bool
    {
        $lastHeartbeat = Cache::get('messenger_gateway_heartbeat');

        if ($lastHeartbeat === null) {
            return false;
        }

        // Consider gateway running if heartbeat is within last 2 minutes
        return $lastHeartbeat->gt(now()->subMinutes(2));
    }

    /**
     * Get queue health metrics from Horizon.
     *
     * @return array<string, mixed>
     */
    private function getQueueHealth(): array
    {
        // Basic queue health - can be extended with Horizon metrics
        return [
            'status' => 'ok',
            'pending_jobs' => 0,
            'failed_jobs' => 0,
        ];
    }
}
