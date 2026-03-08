<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use App\Support\Connectors\CircuitBreaker;
use Illuminate\Pipeline\Pipeline;

class ConnectorExecutionPipeline
{
    public function __construct(
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    public function execute(ActionRequest $request): ActionResponse
    {
        $this->circuitBreaker->check($request->connector->id);

        try {
            $result = app(Pipeline::class)
                ->send($request)
                ->through($this->stages())
                ->then(function (ActionRequest $request) {
                    return $request->connection->getAttribute('_action_response')
                        ?? new ActionResponse(status: 200, data: []);
                });

            if ($result instanceof ActionResponse && $result->isSuccess()) {
                $this->circuitBreaker->recordSuccess($request->connector->id);
            } else {
                $this->circuitBreaker->recordFailure($request->connector->id);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->circuitBreaker->recordFailure($request->connector->id);
            throw $e;
        }
    }

    private function stages(): array
    {
        return [
            ConnectorResolver::class,
            ConnectorPolicyGate::class,
            ApprovalGate::class,
            app()->make(CredentialManager::class),
            ConnectorRateLimiter::class,
            ConnectorHttpClient::class,
            ResponseNormalizer::class,
            PiiRedactor::class,
            app()->make(TelemetryEmitter::class),
        ];
    }
}
