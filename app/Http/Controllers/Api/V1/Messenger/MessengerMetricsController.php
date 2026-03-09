<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messenger;

use App\Actions\Connector\ListConnectorStatusByProviderAction;
use App\Http\Controllers\Controller;
use App\Support\Messenger\MetricsCollector;
use Illuminate\Http\JsonResponse;

class MessengerMetricsController extends Controller
{
    public function __construct(
        private readonly MetricsCollector $metricsCollector,
        private readonly ListConnectorStatusByProviderAction $listConnectorStatusByProvider,
    ) {}

    public function index(): JsonResponse
    {
        $metrics = $this->metricsCollector->getMetrics();

        $connectorStatus = $this->listConnectorStatusByProvider->execute();

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
