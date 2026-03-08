<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Support\Connectors\ActionRequest;
use Closure;
use RuntimeException;

class ConnectorPolicyGate
{
    public function handle(ActionRequest $request, Closure $next): mixed
    {
        $action = $this->findAction($request);
        $method = strtoupper($action['method'] ?? 'GET');
        $isWriteAction = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);

        if ($isWriteAction) {
            if (! config('connectors.write_actions')) {
                throw new RuntimeException('Write actions are disabled.');
            }

            $trustScore = $request->trustScore ?? 0.0;
            $riskLevel = $request->connector->risk_level ?? 'standard';

            $threshold = match ($riskLevel) {
                'elevated' => 0.7,
                default => $request->connector->write_trust_threshold ?? 0.5,
            };

            if ($trustScore < $threshold) {
                throw new RuntimeException(
                    "Insufficient trust score ({$trustScore}) for write action. Required: {$threshold}."
                );
            }
        }

        return $next($request);
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
