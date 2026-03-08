<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ActionResponse;
use Closure;

class PiiRedactor
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        if (! config('connectors.pii_redaction.enabled', true)) {
            return $next($request);
        }

        $response = $request->connection->getAttribute('_action_response');

        if (! $response instanceof ActionResponse) {
            return $next($request);
        }

        $piiFields = $this->getPiiFields($request);

        if (empty($piiFields)) {
            return $next($request);
        }

        $replacement = config('connectors.pii_redaction.replacement', '[REDACTED]');
        $redactedData = $this->redactFields($response->data, $piiFields, $replacement);

        $redacted = new ActionResponse(
            status: $response->status,
            data: $redactedData,
            headers: $response->headers,
            durationMs: $response->durationMs,
            rawResponse: $response->rawResponse,
        );

        $request->connection->setAttribute('_action_response', $redacted);

        return $next($request);
    }

    public function redactFields(array $data, array $piiFields, string $replacement): array
    {
        foreach ($piiFields as $fieldPath) {
            $data = $this->redactPath($data, explode('.', $fieldPath), $replacement);
        }

        return $data;
    }

    private function redactPath(array $data, array $segments, string $replacement): array
    {
        if (empty($segments)) {
            return $data;
        }

        $key = array_shift($segments);

        if (! array_key_exists($key, $data)) {
            return $data;
        }

        if (empty($segments)) {
            $data[$key] = $replacement;

            return $data;
        }

        if (is_array($data[$key])) {
            $data[$key] = $this->redactPath($data[$key], $segments, $replacement);
        }

        return $data;
    }

    private function getPiiFields(ActionRequest $request): array
    {
        $actions = $request->connector->actions ?? [];

        foreach ($actions as $action) {
            if (($action['name'] ?? '') === $request->action) {
                return $action['pii_fields'] ?? [];
            }
        }

        return [];
    }
}
