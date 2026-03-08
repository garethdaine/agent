<?php

declare(strict_types=1);

namespace App\Messenger\Reliability;

use App\Messenger\Exceptions\CircuitOpenException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Per-connector circuit breaker with configurable thresholds.
 *
 * Implements a three-state circuit breaker pattern:
 * - Closed: Normal operation, requests allowed
 * - Open: Circuit tripped after failures, requests rejected
 * - Half-Open: Testing recovery after cooldown, limited requests allowed
 *
 * State transitions:
 * - Closed → Open: After failure_threshold consecutive failures
 * - Open → Half-Open: After cooldown_seconds elapsed
 * - Half-Open → Closed: After half_open_requests successful requests
 * - Half-Open → Open: On any failure during half-open
 */
class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    private const CACHE_KEY_PREFIX = 'circuit_breaker:';

    private const CACHE_TTL_SECONDS = 600; // 10 minutes

    public function __construct(
        private readonly int|string $connectorId,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60,
        private readonly int $halfOpenRequests = 3
    ) {}

    /**
     * Create a circuit breaker for a connector using config defaults.
     */
    public static function forConnector(int|string $connectorId): self
    {
        return new self(
            connectorId: $connectorId,
            failureThreshold: config('messenger.circuit_breaker.failure_threshold', 5),
            cooldownSeconds: config('messenger.circuit_breaker.cooldown_seconds', 60),
            halfOpenRequests: config('messenger.circuit_breaker.half_open_requests', 3)
        );
    }

    /**
     * Check if a request can be made through this circuit.
     *
     * @throws CircuitOpenException When circuit is open and cooldown not expired
     */
    public function canRequest(): bool
    {
        $state = $this->getState();

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_OPEN) {
            if ($this->cooldownExpired()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
                // This first request counts toward half-open limit
                $this->incrementHalfOpenRequestCount();

                return true;
            }

            throw new CircuitOpenException($this->connectorId);
        }

        if ($state === self::STATE_HALF_OPEN) {
            // Check if we've already allowed the max half-open requests
            $halfOpenCount = $this->getHalfOpenRequestCount();
            if ($halfOpenCount < $this->halfOpenRequests) {
                $this->incrementHalfOpenRequestCount();

                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $this->incrementHalfOpenSuccess();

            if ($this->getHalfOpenSuccessCount() >= $this->halfOpenRequests) {
                $this->transitionTo(self::STATE_CLOSED);
            }
        } else {
            $this->resetFailures();
        }
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo(self::STATE_OPEN);
        } else {
            $this->incrementFailures();

            if ($this->getFailureCount() >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
            }
        }
    }

    /**
     * Get the current circuit state.
     */
    public function getState(): string
    {
        $data = $this->getStateData();

        return $data['state'] ?? self::STATE_CLOSED;
    }

    /**
     * Get the current failure count.
     */
    public function getFailureCount(): int
    {
        $data = $this->getStateData();

        return $data['failures'] ?? 0;
    }

    /**
     * Get the count of successful half-open requests.
     */
    public function getHalfOpenSuccessCount(): int
    {
        $data = $this->getStateData();

        return $data['half_open_success'] ?? 0;
    }

    /**
     * Get when the cooldown expires (for monitoring).
     */
    public function getCooldownExpiresAt(): ?int
    {
        $data = $this->getStateData();

        if (($data['state'] ?? null) !== self::STATE_OPEN) {
            return null;
        }

        return $data['cooldown_expires_at'] ?? null;
    }

    /**
     * Get the count of requests made during half-open state.
     */
    private function getHalfOpenRequestCount(): int
    {
        $data = $this->getStateData();

        return $data['half_open_requests'] ?? 0;
    }

    /**
     * Increment the half-open request count.
     */
    private function incrementHalfOpenRequestCount(): void
    {
        $data = $this->getStateData();
        $data['half_open_requests'] = ($data['half_open_requests'] ?? 0) + 1;
        $this->saveStateData($data);
    }

    /**
     * Check if the cooldown period has expired.
     */
    private function cooldownExpired(): bool
    {
        $data = $this->getStateData();
        $expiresAt = $data['cooldown_expires_at'] ?? 0;

        return now()->timestamp >= $expiresAt;
    }

    /**
     * Transition to a new state.
     */
    private function transitionTo(string $newState): void
    {
        $currentState = $this->getState();

        Log::info('Circuit breaker transition', [
            'connector_id' => $this->connectorId,
            'from' => $currentState,
            'to' => $newState,
        ]);

        $data = [
            'state' => $newState,
            'failures' => 0,
            'half_open_success' => 0,
            'half_open_requests' => 0,
            'last_transition_at' => now()->timestamp,
        ];

        if ($newState === self::STATE_OPEN) {
            $data['cooldown_expires_at'] = now()->addSeconds($this->cooldownSeconds)->timestamp;
        }

        $this->saveStateData($data);
    }

    /**
     * Increment the failure count.
     */
    private function incrementFailures(): void
    {
        $data = $this->getStateData();
        $data['failures'] = ($data['failures'] ?? 0) + 1;
        $data['last_failure_at'] = now()->timestamp;
        $this->saveStateData($data);
    }

    /**
     * Increment the half-open success count.
     */
    private function incrementHalfOpenSuccess(): void
    {
        $data = $this->getStateData();
        $data['half_open_success'] = ($data['half_open_success'] ?? 0) + 1;
        $this->saveStateData($data);
    }

    /**
     * Reset the failure count.
     */
    private function resetFailures(): void
    {
        $data = $this->getStateData();
        $data['failures'] = 0;
        $this->saveStateData($data);
    }

    /**
     * Get the cache key for this circuit breaker.
     */
    private function cacheKey(): string
    {
        return self::CACHE_KEY_PREFIX.$this->connectorId;
    }

    /**
     * Get the current state data from cache.
     *
     * @return array<string, mixed>
     */
    private function getStateData(): array
    {
        return Cache::get($this->cacheKey(), []);
    }

    /**
     * Save state data to cache.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveStateData(array $data): void
    {
        Cache::put($this->cacheKey(), $data, self::CACHE_TTL_SECONDS);
    }
}
