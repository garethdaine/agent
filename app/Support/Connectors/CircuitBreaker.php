<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Support\Connectors\Exceptions\CircuitOpenException;
use Illuminate\Support\Facades\Redis;

class CircuitBreaker
{
    public function check(string $connectorId): void
    {
        $state = $this->getState($connectorId);

        if (! $state) {
            return;
        }

        $openUntil = $state['open_until'] ?? 0;

        if ($openUntil > time()) {
            $recoversIn = $openUntil - time();
            throw new CircuitOpenException($connectorId, $recoversIn);
        }
    }

    public function isOpen(string $connectorId): bool
    {
        $state = $this->getState($connectorId);

        if (! $state) {
            return false;
        }

        return ($state['open_until'] ?? 0) > time();
    }

    public function isHalfOpen(string $connectorId): bool
    {
        $state = $this->getState($connectorId);

        if (! $state) {
            return false;
        }

        $openUntil = $state['open_until'] ?? 0;

        return $openUntil > 0 && $openUntil <= time() && ($state['failure_count'] ?? 0) >= $this->failureThreshold();
    }

    public function recordFailure(string $connectorId): void
    {
        $key = $this->key($connectorId);
        $state = $this->getState($connectorId) ?? [
            'failure_count' => 0,
            'last_failure_at' => 0,
            'open_until' => 0,
            'window_start' => time(),
        ];

        $windowSeconds = config('connectors.circuit_breaker.failure_window_seconds', 60);

        if (time() - ($state['window_start'] ?? 0) > $windowSeconds) {
            $state['failure_count'] = 0;
            $state['window_start'] = time();
        }

        $state['failure_count']++;
        $state['last_failure_at'] = time();

        if ($state['failure_count'] >= $this->failureThreshold()) {
            $recoveryTimeout = config('connectors.circuit_breaker.recovery_timeout_seconds', 120);
            $state['open_until'] = time() + $recoveryTimeout;
        }

        Redis::setex($key, $windowSeconds + config('connectors.circuit_breaker.recovery_timeout_seconds', 120), json_encode($state));
    }

    public function recordSuccess(string $connectorId): void
    {
        $this->reset($connectorId);
    }

    public function reset(string $connectorId): void
    {
        Redis::del($this->key($connectorId));
    }

    private function getState(string $connectorId): ?array
    {
        $data = Redis::get($this->key($connectorId));

        if (! $data) {
            return null;
        }

        return json_decode($data, true);
    }

    private function key(string $connectorId): string
    {
        return "connector_circuit:{$connectorId}";
    }

    private function failureThreshold(): int
    {
        return (int) config('connectors.circuit_breaker.failure_threshold', 5);
    }
}
