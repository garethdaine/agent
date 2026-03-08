<?php

declare(strict_types=1);

namespace App\Http\Middleware\Messenger;

use App\Messenger\Observability\CorrelationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function __construct(
        private readonly CorrelationContext $correlationContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Generate a fresh correlation ID for this message lifecycle
        // Accept incoming X-Correlation-Id header for request tracing, or generate new
        $correlationId = $request->header('X-Correlation-Id') ?? (string) Str::uuid();

        // Store in request attributes (existing behavior)
        $request->attributes->set('correlation_id', $correlationId);

        // Store in Laravel Context for logging integration
        Context::add('messenger_correlation_id', $correlationId);

        $response = $next($request);

        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
