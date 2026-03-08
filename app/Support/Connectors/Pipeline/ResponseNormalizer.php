<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use Closure;

class ResponseNormalizer
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        $response = $request->connection->getAttribute('_action_response');

        if (! $response instanceof ActionResponse) {
            return $next($request);
        }

        $normalizedData = $this->normalize($response->data, $request);

        $normalized = new ActionResponse(
            status: $response->status,
            data: $normalizedData,
            headers: $response->headers,
            durationMs: $response->durationMs,
            rawResponse: $response->rawResponse,
        );

        $request->connection->setAttribute('_action_response', $normalized);

        return $next($request);
    }

    private function normalize(array $data, ActionRequest $request): array
    {
        $action = $this->findAction($request);
        $responseSchema = $action['response_schema'] ?? null;

        if (! $responseSchema) {
            return $data;
        }

        $dataKey = $responseSchema['data_key'] ?? null;
        if ($dataKey && isset($data[$dataKey])) {
            $data = ['data' => $data[$dataKey]];
        }

        return $data;
    }

    private function findAction(ActionRequest $request): array
    {
        $actions = $request->connector->actions ?? [];

        foreach ($actions as $action) {
            if (($action['name'] ?? '') === $request->action) {
                return $action;
            }
        }

        return [];
    }
}
