<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Actions\Connector\CheckWebhookEventExistsAction;
use App\Actions\Connector\CreateWebhookEventAction;
use App\Actions\Connector\FindAgentConnectorAction;
use App\Actions\Connector\FindConnectorConnectionAction;
use App\Http\Controllers\Controller;
use App\Jobs\Connectors\ProcessConnectorWebhookJob;
use App\Models\AgentConnectorWebhookEvent;
use App\Support\Connectors\Webhooks\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorWebhookController extends Controller
{
    public function __construct(
        private readonly FindAgentConnectorAction $findAgentConnector,
        private readonly FindConnectorConnectionAction $findConnectorConnection,
        private readonly CheckWebhookEventExistsAction $checkWebhookEventExists,
        private readonly CreateWebhookEventAction $createWebhookEvent,
    ) {}

    public function __invoke(
        Request $request,
        WebhookSignatureVerifier $verifier,
        string $connectorName,
        string $event,
    ): JsonResponse {
        if (! config('connectors.webhooks_enabled')) {
            return response()->json(['error' => 'Webhooks disabled'], 404);
        }

        $connector = $this->findAgentConnector->findByName($connectorName);

        if (! $connector) {
            // Return 200 for unknown connectors to prevent providers from disabling webhooks
            return response()->json(['received' => true]);
        }

        $connection = $this->findConnectorConnection->findConnectedForConnector($connector->id);

        if (! $connection) {
            return response()->json(['received' => true]);
        }

        $webhookSecret = $connection->config['webhook_secret'] ?? null;

        if (! $webhookSecret) {
            return response()->json(['received' => true]);
        }

        $payload = $request->getContent();
        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Signature')
            ?? '';

        if (! $verifier->verify($payload, $signature, $webhookSecret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $externalEventId = $request->header('X-Webhook-Event-Id', (string) \Illuminate\Support\Str::uuid());

        // Deduplicate by (connection_id, external_event_id)
        if ($this->checkWebhookEventExists->execute($connection->id, $externalEventId)) {
            return response()->json(['received' => true, 'deduplicated' => true]);
        }

        $webhookEvent = $this->createWebhookEvent->execute([
            'connection_id' => $connection->id,
            'connector_id' => $connector->id,
            'event_type' => $event,
            'external_event_id' => $externalEventId,
            'payload_hash' => hash('sha256', $payload),
            'processing_status' => AgentConnectorWebhookEvent::STATUS_RECEIVED,
            'created_at' => now(),
        ]);

        ProcessConnectorWebhookJob::dispatch($webhookEvent);

        return response()->json(['received' => true]);
    }
}
