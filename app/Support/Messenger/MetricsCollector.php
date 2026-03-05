<?php

namespace App\Support\Messenger;

use App\Models\AgentAuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetricsCollector
{
    private const CACHE_PREFIX = 'messenger_metrics:';

    private const CACHE_TTL = 3600;

    public function incrementInboundMessages(string $provider): void
    {
        $key = $this->key("inbound:{$provider}");
        Cache::increment($key);
        Cache::put("{$key}:ttl", now()->addSeconds(self::CACHE_TTL)->timestamp, self::CACHE_TTL);
    }

    public function incrementActionSuccess(string $actionType): void
    {
        $key = $this->key("actions:{$actionType}:success");
        Cache::increment($key);
        Cache::put("{$key}:ttl", now()->addSeconds(self::CACHE_TTL)->timestamp, self::CACHE_TTL);
    }

    public function incrementActionFailure(string $actionType, string $errorCode): void
    {
        $failureKey = $this->key("actions:{$actionType}:failure");
        $errorKey = $this->key("actions:{$actionType}:errors:{$errorCode}");

        Cache::increment($failureKey);
        Cache::increment($errorKey);

        Cache::put("{$failureKey}:ttl", now()->addSeconds(self::CACHE_TTL)->timestamp, self::CACHE_TTL);
        Cache::put("{$errorKey}:ttl", now()->addSeconds(self::CACHE_TTL)->timestamp, self::CACHE_TTL);
    }

    public function recordActionLatency(string $actionType, float $milliseconds): void
    {
        $latencyKey = $this->key("latency:{$actionType}");

        $current = Cache::get($latencyKey, [
            'sum' => 0.0,
            'count' => 0,
            'min' => PHP_FLOAT_MAX,
            'max' => 0.0,
        ]);

        $current['sum'] += $milliseconds;
        $current['count']++;
        $current['min'] = min($current['min'], $milliseconds);
        $current['max'] = max($current['max'], $milliseconds);

        Cache::put($latencyKey, $current, self::CACHE_TTL);
    }

    public function incrementWebhookVerificationFailure(string $provider): void
    {
        $key = $this->key("webhook_failures:{$provider}");
        Cache::increment($key);
        Cache::put("{$key}:ttl", now()->addSeconds(self::CACHE_TTL)->timestamp, self::CACHE_TTL);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'inbound_messages' => $this->getInboundMetrics(),
            'actions' => $this->getActionMetrics(),
            'latency' => $this->getLatencyMetrics(),
            'webhook_failures' => $this->getWebhookFailureMetrics(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getInboundMetrics(): array
    {
        $providers = ['slack', 'telegram', 'discord', 'whatsapp'];
        $metrics = [];

        foreach ($providers as $provider) {
            $value = (int) Cache::get($this->key("inbound:{$provider}"), 0);
            if ($value > 0) {
                $metrics[$provider] = $value;
            }
        }

        return $metrics;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getActionMetrics(): array
    {
        $actionTypes = [
            'jobs.create',
            'jobs.update',
            'jobs.delete',
            'jobs.list',
            'runs.list_active',
            'runs.stop',
            'runs.retry',
            'runs.run_now',
            'runs.steer',
        ];

        $metrics = [];

        foreach ($actionTypes as $actionType) {
            $success = (int) Cache::get($this->key("actions:{$actionType}:success"), 0);
            $failure = (int) Cache::get($this->key("actions:{$actionType}:failure"), 0);

            if ($success > 0 || $failure > 0) {
                $metrics[$actionType] = [
                    'success' => $success,
                    'failure' => $failure,
                    'errors' => $this->getActionErrors($actionType),
                ];
            }
        }

        return $metrics;
    }

    /**
     * @return array<string, int>
     */
    private function getActionErrors(string $actionType): array
    {
        $commonErrors = ['TIMEOUT', 'UNAUTHORIZED', 'NOT_FOUND', 'VALIDATION_ERROR', 'INTERNAL_ERROR'];
        $errors = [];

        foreach ($commonErrors as $errorCode) {
            $value = (int) Cache::get($this->key("actions:{$actionType}:errors:{$errorCode}"), 0);
            if ($value > 0) {
                $errors[$errorCode] = $value;
            }
        }

        return $errors;
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function getLatencyMetrics(): array
    {
        $actionTypes = [
            'jobs.create',
            'jobs.update',
            'jobs.delete',
            'jobs.list',
            'runs.list_active',
            'runs.stop',
            'runs.retry',
            'runs.run_now',
            'runs.steer',
        ];

        $metrics = [];

        foreach ($actionTypes as $actionType) {
            $latency = Cache::get($this->key("latency:{$actionType}"));

            if ($latency && $latency['count'] > 0) {
                $metrics[$actionType] = [
                    'avg_ms' => round($latency['sum'] / $latency['count'], 2),
                    'min_ms' => $latency['min'] === PHP_FLOAT_MAX ? 0.0 : $latency['min'],
                    'max_ms' => $latency['max'],
                    'count' => $latency['count'],
                ];
            }
        }

        return $metrics;
    }

    /**
     * @return array<string, int>
     */
    private function getWebhookFailureMetrics(): array
    {
        $providers = ['slack', 'telegram', 'discord', 'whatsapp'];
        $metrics = [];

        foreach ($providers as $provider) {
            $value = (int) Cache::get($this->key("webhook_failures:{$provider}"), 0);
            if ($value > 0) {
                $metrics[$provider] = $value;
            }
        }

        return $metrics;
    }

    /**
     * Persist current Redis metrics to the audit log for durable storage,
     * then reset the in-memory counters.
     *
     * Designed to be called from a scheduled command (e.g. hourly).
     */
    public function persistAndReset(): void
    {
        $metrics = $this->getMetrics();

        $hasData = collect($metrics)->contains(fn ($section) => ! empty($section));

        if (! $hasData) {
            return;
        }

        try {
            AgentAuditLog::create([
                'actor_type' => 'system',
                'action' => 'messenger.metrics.snapshot',
                'context' => $metrics,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist messenger metrics', ['error' => $e->getMessage()]);

            return;
        }

        $this->resetMetrics();
    }

    public function resetMetrics(): void
    {
        $patterns = [
            'inbound:*',
            'actions:*',
            'latency:*',
            'webhook_failures:*',
        ];

        foreach ($patterns as $pattern) {
            // Note: This is a simplified reset - in production you may want
            // to use Redis SCAN or similar for large datasets
            $providers = ['slack', 'telegram', 'discord', 'whatsapp'];
            foreach ($providers as $provider) {
                Cache::forget($this->key("inbound:{$provider}"));
                Cache::forget($this->key("webhook_failures:{$provider}"));
            }

            $actionTypes = [
                'jobs.create', 'jobs.update', 'jobs.delete', 'jobs.list',
                'runs.list_active', 'runs.stop', 'runs.retry', 'runs.run_now', 'runs.steer',
            ];
            foreach ($actionTypes as $actionType) {
                Cache::forget($this->key("actions:{$actionType}:success"));
                Cache::forget($this->key("actions:{$actionType}:failure"));
                Cache::forget($this->key("latency:{$actionType}"));
            }
        }
    }

    private function key(string $suffix): string
    {
        return self::CACHE_PREFIX.$suffix;
    }
}
