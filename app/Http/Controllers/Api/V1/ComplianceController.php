<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Compliance\ComplianceFlagResolver;
use Illuminate\Http\JsonResponse;

class ComplianceController extends Controller
{
    public function __construct(
        private readonly ComplianceFlagResolver $flagResolver
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => config('agent.compliance.enabled', false),
            'enforcement_mode' => $this->flagResolver->getEffectiveMode(),
            'flags' => $this->flagResolver->resolveAll(),
        ]);
    }

    public function metrics(): JsonResponse
    {
        // TODO: Implement actual metrics collection from events/database
        return response()->json([
            'period' => 'last_24h',
            'gate_evaluations' => 0,
            'pass_rate' => null,
            'block_rate' => null,
            'top_block_reasons' => [],
        ]);
    }
}
