<?php

declare(strict_types=1);

namespace App\Messenger\Gateway;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Implements exponential backoff with jitter for gateway worker reconnections.
 *
 * The strategy prevents thundering herd problems when multiple workers
 * attempt to reconnect simultaneously after a network partition.
 *
 * Delay calculation: base_delay * 2^attempt * (1 ± jitter)
 *
 * Configuration values from config('messenger.gateway.reconnect'):
 * - initial_delay: Starting delay in seconds (default: 1)
 * - max_delay: Maximum delay cap in seconds (default: 300 = 5 minutes)
 * - jitter_percent: Percentage of randomization (default: 20 = ±20%)
 *
 * The attempt counter resets after a stable connection lasting over 60 seconds.
 */
class ReconnectionStrategy
{
    private int $attemptCount = 0;

    private ?CarbonInterface $connectionStartedAt = null;

    private const STABLE_CONNECTION_THRESHOLD_SECONDS = 60;

    public function __construct(
        private readonly ?float $initialDelay = null,
        private readonly ?float $maxDelay = null,
        private readonly ?float $jitterPercent = null,
    ) {}

    /**
     * Calculate the next delay for a given attempt number.
     *
     * @param  int  $attempt  The attempt number (0-indexed)
     * @return float Delay in seconds
     */
    public function getNextDelay(int $attempt): float
    {
        $initialDelay = $this->getInitialDelay();
        $maxDelay = $this->getMaxDelay();
        $jitterPercent = $this->getJitterPercent();

        // Calculate base delay with exponential backoff
        $baseDelay = $initialDelay * pow(2, $attempt);

        // Cap at maximum
        $cappedDelay = min($baseDelay, $maxDelay);

        // Apply jitter
        $delay = $this->applyJitter($cappedDelay, $jitterPercent);

        Log::debug('Reconnection delay calculated', [
            'attempt' => $attempt,
            'base_delay' => $baseDelay,
            'capped_delay' => $cappedDelay,
            'delay_with_jitter' => $delay,
        ]);

        return $delay;
    }

    /**
     * Record a reconnection attempt.
     */
    public function recordAttempt(): void
    {
        $this->attemptCount++;
        $this->connectionStartedAt = null;

        Log::debug('Reconnection attempt recorded', [
            'attempt_count' => $this->attemptCount,
        ]);
    }

    /**
     * Get the current attempt count.
     */
    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    /**
     * Reset the attempt counter.
     */
    public function resetAttempts(): void
    {
        $this->attemptCount = 0;
        $this->connectionStartedAt = null;

        Log::debug('Reconnection attempts reset');
    }

    /**
     * Record the start of a successful connection.
     */
    public function recordConnectionStart(): void
    {
        $this->connectionStartedAt = now();

        Log::debug('Connection start recorded', [
            'started_at' => $this->connectionStartedAt->toIso8601String(),
        ]);
    }

    /**
     * Check if the connection has been stable long enough to reset attempts.
     */
    public function shouldResetAttempts(): bool
    {
        if ($this->connectionStartedAt === null) {
            return false;
        }

        $uptimeSeconds = $this->connectionStartedAt->diffInSeconds(now());

        return $uptimeSeconds > self::STABLE_CONNECTION_THRESHOLD_SECONDS;
    }

    /**
     * Reset attempts if the connection has been stable.
     */
    public function resetIfStable(): void
    {
        if ($this->shouldResetAttempts()) {
            Log::info('Connection stable, resetting reconnection attempts', [
                'uptime_seconds' => $this->connectionStartedAt?->diffInSeconds(now()),
            ]);
            $this->resetAttempts();
        }
    }

    /**
     * Get the next delay based on internal attempt counter and increment it.
     */
    public function nextDelay(): float
    {
        $delay = $this->getNextDelay($this->attemptCount);
        $this->recordAttempt();

        return $delay;
    }

    /**
     * Apply jitter to a delay value.
     */
    private function applyJitter(float $delay, float $jitterPercent): float
    {
        if ($jitterPercent <= 0) {
            return $delay;
        }

        // Generate random factor between (1 - jitter%) and (1 + jitter%)
        $jitterFactor = 1 + (random_int(-100, 100) / 100) * ($jitterPercent / 100);

        return $delay * $jitterFactor;
    }

    /**
     * Get initial delay from config or constructor.
     */
    private function getInitialDelay(): float
    {
        if ($this->initialDelay !== null) {
            return $this->initialDelay;
        }

        return (float) config('messenger.gateway.reconnect.initial_delay', 1);
    }

    /**
     * Get max delay from config or constructor.
     */
    private function getMaxDelay(): float
    {
        if ($this->maxDelay !== null) {
            return $this->maxDelay;
        }

        return (float) config('messenger.gateway.reconnect.max_delay', 300);
    }

    /**
     * Get jitter percent from config or constructor.
     */
    private function getJitterPercent(): float
    {
        if ($this->jitterPercent !== null) {
            return $this->jitterPercent;
        }

        return (float) config('messenger.gateway.reconnect.jitter_percent', 20);
    }
}
