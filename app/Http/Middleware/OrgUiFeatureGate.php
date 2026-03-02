<?php

namespace App\Http\Middleware;

use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;

class OrgUiFeatureGate
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $this->featureFlags->isEnabled(FeatureFlagManager::ORG_ENABLED)
            && ! config('agent.org.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
