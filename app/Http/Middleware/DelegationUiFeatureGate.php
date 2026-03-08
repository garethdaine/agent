<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;

class DelegationUiFeatureGate
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::DELEGATION_UI_ENABLED)) {
            abort(403, 'Delegation UI is not enabled');
        }

        return $next($request);
    }
}
