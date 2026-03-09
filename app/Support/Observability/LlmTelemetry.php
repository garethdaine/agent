<?php

declare(strict_types=1);

namespace App\Support\Observability;

use Illuminate\Support\Facades\Log;

/**
 * Lightweight LLM telemetry service for tracking model API call metrics.
 *
 * Since no PHP OpenLLMetry package exists, this service provides structured
 * logging of LLM API calls including model, token counts, estimated cost,
 * and latency for observability and cost tracking.
 */
class LlmTelemetry
{
    /**
     * Cost per 1M tokens for common models (input/output).
     *
     * @var array<string, array{input: float, output: float}>
     */
    private const MODEL_PRICING = [
        'claude-3-haiku-20240307' => ['input' => 0.25, 'output' => 1.25],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-sonnet-4-20250514' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-4-20250514' => ['input' => 15.00, 'output' => 75.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
        'text-embedding-3-small' => ['input' => 0.02, 'output' => 0.00],
    ];

    /**
     * Record an LLM API call with telemetry data.
     *
     * @param  string  $provider  Provider name (e.g., 'anthropic', 'openai')
     * @param  string  $model  Model identifier
     * @param  int  $tokensIn  Input token count
     * @param  int  $tokensOut  Output token count
     * @param  float  $latencyMs  Request latency in milliseconds
     * @param  string  $operation  Operation type (e.g., 'extraction', 'embedding', 'summarization')
     * @param  array<string, mixed>  $extra  Additional context data
     */
    public function record(
        string $provider,
        string $model,
        int $tokensIn,
        int $tokensOut,
        float $latencyMs,
        string $operation = 'inference',
        array $extra = [],
    ): void {
        $cost = $this->estimateCost($model, $tokensIn, $tokensOut);

        Log::channel('json')->info('llm.api_call', array_merge([
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'tokens_total' => $tokensIn + $tokensOut,
            'cost_usd' => $cost,
            'latency_ms' => round($latencyMs, 2),
        ], $extra));
    }

    /**
     * Record a failed LLM API call.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model identifier
     * @param  string  $error  Error message
     * @param  float  $latencyMs  Request latency in milliseconds
     * @param  string  $operation  Operation type
     */
    public function recordFailure(
        string $provider,
        string $model,
        string $error,
        float $latencyMs,
        string $operation = 'inference',
    ): void {
        Log::channel('json')->warning('llm.api_call_failed', [
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'error' => $error,
            'latency_ms' => round($latencyMs, 2),
        ]);
    }

    /**
     * Estimate the cost of an LLM API call in USD.
     *
     * @param  string  $model  Model identifier
     * @param  int  $tokensIn  Input token count
     * @param  int  $tokensOut  Output token count
     * @return float Estimated cost in USD
     */
    public function estimateCost(string $model, int $tokensIn, int $tokensOut): float
    {
        $pricing = self::MODEL_PRICING[$model] ?? null;

        if ($pricing === null) {
            return 0.0;
        }

        $inputCost = ($tokensIn / 1_000_000) * $pricing['input'];
        $outputCost = ($tokensOut / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 8);
    }
}
