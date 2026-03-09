<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use Closure;
use Illuminate\Support\Facades\Http;

class ConnectorHttpClient
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        $action = $this->findAction($request);
        $method = strtoupper($action['method'] ?? 'GET');
        $path = $action['path'] ?? '/';
        $url = rtrim($request->connector->base_url, '/').'/'.ltrim($path, '/');
        $timeout = $action['timeout_seconds'] ?? config('connectors.default_action_timeout_seconds', 30);

        $authHeaders = $request->connection->getAttribute('_auth_headers') ?? [];
        $retryConfig = config('connectors.retry');
        $maxAttempts = $retryConfig['max_attempts'] ?? 3;
        $backoffSeconds = $retryConfig['backoff_seconds'] ?? [1, 2, 4];

        $startTime = microtime(true);
        $retryCount = 0;
        $lastResponse = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $pendingRequest = Http::withHeaders($authHeaders)
                ->timeout($timeout);

            try {
                $lastResponse = match ($method) {
                    'POST' => $pendingRequest->post($url, $request->parameters),
                    'PUT' => $pendingRequest->put($url, $request->parameters),
                    'PATCH' => $pendingRequest->patch($url, $request->parameters),
                    'DELETE' => $pendingRequest->delete($url, $request->parameters),
                    default => $pendingRequest->get($url, $request->parameters),
                };
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $durationMs = (microtime(true) - $startTime) * 1000;

                $actionResponse = new ActionResponse(
                    status: 0,
                    data: ['error' => 'Connection timeout', 'message' => $e->getMessage()],
                    durationMs: $durationMs,
                );

                $request->connection->setAttribute('_action_response', $actionResponse);
                $request->connection->setAttribute('_retry_count', $retryCount);

                return $next($request);
            }

            $status = $lastResponse->status();

            if ($this->shouldRetry($status) && $attempt < $maxAttempts - 1) {
                $retryCount++;
                $delay = ($backoffSeconds[$attempt] ?? end($backoffSeconds));
                $jitter = $delay * (random_int(0, 100) / 1000);
                usleep((int) (($delay + $jitter) * 1_000_000));

                continue;
            }

            break;
        }

        $durationMs = (microtime(true) - $startTime) * 1000;

        $actionResponse = new ActionResponse(
            status: $lastResponse->status(),
            data: $lastResponse->json() ?? [],
            headers: $lastResponse->headers(),
            durationMs: $durationMs,
            rawResponse: $lastResponse->body(),
        );

        $request->connection->setAttribute('_action_response', $actionResponse);
        $request->connection->setAttribute('_retry_count', $retryCount);

        return $next($request);
    }

    private function shouldRetry(int $status): bool
    {
        return in_array($status, [429, 500, 502, 503, 504]);
    }

    private function findAction(ActionRequest $request): array
    {
        $actions = $request->connector->actions ?? [];

        foreach ($actions as $action) {
            if (($action['name'] ?? '') === $request->action) {
                return $action;
            }
        }

        return ['method' => 'GET', 'path' => '/'];
    }
}
