<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use App\Support\Connectors\ConnectorHealthScorer;
use Closure;

class TelemetryEmitter
{
    public function __construct(
        private readonly ConnectorHealthScorer $healthScorer,
    ) {}

    public function handle(ActionRequest $request, Closure $next): mixed
    {
        $response = $request->connection->getAttribute('_action_response');
        $retryCount = (int) $request->connection->getAttribute('_retry_count');

        $outcome = $this->determineOutcome($response);
        $action = $this->findAction($request);

        $invocation = AgentConnectorInvocation::create([
            'connection_id' => $request->connection->id,
            'connector_id' => $request->connector->id,
            'action_name' => $request->action,
            'run_attempt_id' => $request->runAttemptId,
            'delegatee_id' => $request->delegateeId,
            'workflow_key' => $request->workflowKey,
            'http_method' => strtoupper($action['method'] ?? 'GET'),
            'http_status' => $response?->status ?? 0,
            'duration_ms' => (int) ($response?->durationMs ?? 0),
            'request_size_bytes' => strlen(json_encode($request->parameters) ?: ''),
            'response_size_bytes' => strlen($response?->rawResponse ?? ''),
            'retry_count' => $retryCount,
            'outcome' => $outcome,
            'error_message' => $outcome !== AgentConnectorInvocation::OUTCOME_SUCCESS
                ? $this->extractErrorMessage($response)
                : null,
            'created_at' => now(),
        ]);

        $newScore = $this->healthScorer->calculateQuickScore($request->connection, $outcome);
        AgentConnectorConnection::where('id', $request->connection->id)
            ->update(['health_score' => $newScore]);
        $request->connection->health_score = $newScore;

        $request->connection->setAttribute('_invocation', $invocation);

        return $next($request);
    }

    private function determineOutcome(?ActionResponse $response): string
    {
        if (! $response) {
            return AgentConnectorInvocation::OUTCOME_FAILED;
        }

        if ($response->status === 0) {
            return AgentConnectorInvocation::OUTCOME_TIMEOUT;
        }

        if ($response->status === 429) {
            return AgentConnectorInvocation::OUTCOME_RATE_LIMITED;
        }

        if (in_array($response->status, [401, 403])) {
            return AgentConnectorInvocation::OUTCOME_AUTH_FAILED;
        }

        if ($response->isSuccess()) {
            return AgentConnectorInvocation::OUTCOME_SUCCESS;
        }

        return AgentConnectorInvocation::OUTCOME_FAILED;
    }

    private function extractErrorMessage(?ActionResponse $response): ?string
    {
        if (! $response) {
            return null;
        }

        $error = $response->data['error'] ?? null;

        if (is_array($error)) {
            return json_encode($error);
        }

        return $error ? (string) $error : null;
    }

    private function findAction(ActionRequest $request): array
    {
        $actions = $request->connector->actions ?? [];

        foreach ($actions as $action) {
            if (($action['name'] ?? '') === $request->action) {
                return $action;
            }
        }

        return ['method' => 'GET'];
    }
}
