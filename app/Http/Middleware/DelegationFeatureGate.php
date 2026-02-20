<?php

namespace App\Http\Middleware;

use App\Support\Agent\ErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DelegationFeatureGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('delegation.enabled')) {
            return ErrorEnvelope::make('FEATURE_DISABLED', 'Delegation is not enabled.', 404);
        }

        return $next($request);
    }
}
