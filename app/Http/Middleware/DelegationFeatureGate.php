<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DelegationFeatureGate
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_ENABLED)) {
            return ErrorEnvelope::make('FEATURE_DISABLED', 'Delegation is not enabled.', 404);
        }

        return $next($request);
    }
}
