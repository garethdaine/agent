<?php

declare(strict_types=1);

namespace App\Http\Middleware\Memory;

use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that gates Memory API access.
 *
 * Returns 503 Service Unavailable when memory is not enabled
 * via the FeatureFlagManager (checks DB override, falls back to config).
 */
class MemoryEnabled
{
    public function __construct(
        private FeatureFlagManager $featureFlags
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            return ErrorEnvelope::make(
                'MEMORY_DISABLED',
                'Memory system is not enabled.',
                503
            );
        }

        return $next($request);
    }
}
