<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Http\Controllers\Controller;
use App\Support\Org\OrgCostGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrgCostController extends Controller
{
    public function __construct(
        private readonly OrgCostGovernanceService $costService
    ) {}

    /**
     * Get cost summary with per-agent and per-ritual-run rollups.
     */
    public function summary(Request $request): JsonResponse
    {
        $defaultStartDate = now()->subDays(30);
        $defaultEndDate = now();

        try {
            $startDate = $request->date('start_date') ?? $defaultStartDate;
            $endDate = $request->date('end_date') ?? $defaultEndDate;

            if (! Schema::hasTable('org_cost_ledgers')) {
                return $this->emptySummary($startDate, $endDate);
            }

            $user = $request->user();

            $totalSummary = $this->costService->getUserCostSummary($user, $startDate, $endDate);
            $perAgentBreakdown = $this->costService->getPerAgentCostBreakdown($user, $startDate, $endDate);
            $perRitualRunBreakdown = $this->costService->getPerRitualRunCostBreakdown($user, $startDate, $endDate);

            return response()->json([
                'data' => [
                    'period' => [
                        'start_date' => $startDate->toIso8601String(),
                        'end_date' => $endDate->toIso8601String(),
                    ],
                    'total' => [
                        'total_tokens' => $totalSummary->total_tokens,
                        'total_runtime_ms' => $totalSummary->total_runtime_ms,
                        'total_cost_usd' => $totalSummary->total_cost_usd,
                        'entry_count' => $totalSummary->entry_count,
                    ],
                    'per_agent' => array_map(fn ($item) => [
                        'agent_id' => $item['agent_id'],
                        'total_tokens' => $item['summary']->total_tokens,
                        'total_runtime_ms' => $item['summary']->total_runtime_ms,
                        'total_cost_usd' => $item['summary']->total_cost_usd,
                        'entry_count' => $item['summary']->entry_count,
                    ], $perAgentBreakdown),
                    'per_ritual_run' => array_map(fn ($item) => [
                        'ritual_run_id' => $item['ritual_run_id'],
                        'total_tokens' => $item['summary']->total_tokens,
                        'total_runtime_ms' => $item['summary']->total_runtime_ms,
                        'total_cost_usd' => $item['summary']->total_cost_usd,
                        'entry_count' => $item['summary']->entry_count,
                    ], $perRitualRunBreakdown),
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return $this->emptySummary($defaultStartDate, $defaultEndDate);
        }
    }

    private function emptySummary($startDate, $endDate): JsonResponse
    {
        return response()->json([
            'data' => [
                'period' => [
                    'start_date' => $startDate->toIso8601String(),
                    'end_date' => $endDate->toIso8601String(),
                ],
                'total' => [
                    'total_tokens' => 0,
                    'total_runtime_ms' => 0,
                    'total_cost_usd' => 0,
                    'entry_count' => 0,
                ],
                'per_agent' => [],
                'per_ritual_run' => [],
            ],
        ]);
    }
}
