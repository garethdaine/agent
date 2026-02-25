<?php

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use App\Support\Messenger\MetricsCollector;
use Illuminate\Http\JsonResponse;

class MessengerMetricsController extends Controller
{
    public function __construct(
        private readonly MetricsCollector $metricsCollector
    ) {}

    public function index(): JsonResponse
    {
        $metrics = $this->metricsCollector->getMetrics();

        $connectorStatus = ConnectorAccount::query()
            ->select('id', 'provider', 'name', 'status')
            ->get()
            ->groupBy('provider')
            ->map(fn ($accounts) => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'status' => $account->status,
            ])->values())
            ->toArray();

        return response()->json([
            'data' => [
                'inbound_messages' => $metrics['inbound_messages'],
                'actions' => $metrics['actions'],
                'latency' => $metrics['latency'],
                'webhook_failures' => $metrics['webhook_failures'],
                'connectors' => $connectorStatus,
            ],
            'meta' => [
                'collected_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
