<?php

namespace App\Http\Middleware;

use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrgFeatureGate
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureFlags->isEnabled(FeatureFlagManager::ORG_ENABLED)
            && ! config('agent.org.enabled', false)) {
            return ErrorEnvelope::make('FEATURE_DISABLED', 'Org layer is not enabled.', 404);
        }

        return $next($request);
    }
}
