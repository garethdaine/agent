<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reliability\WorkflowGovernanceSnapshotService;
use App\Services\Telemetry\ActiveBuildFreshnessService;
use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorkflowCostController extends Controller
{
    public function show(
        string $workflowKey,
        ActiveProjectionBuildStateService $buildStateService,
        ActiveBuildFreshnessService $freshnessService,
        WorkflowGovernanceSnapshotService $governanceSnapshotService,
    ): JsonResponse {
        $activeBuildId = $buildStateService->activeProjectionBuildId();
        $freshness = $freshnessService->snapshot();
        $monthUtc = now('UTC')->format('Y-m');

        $baseQuery = DB::table(ProjectionTable::qualified('workflow_cost_rollups'))
            ->where('workflow_key', $workflowKey)
            ->where('budget_month_utc', $monthUtc);

        if ($activeBuildId === null) {
            $baseQuery->whereRaw('1 = 0');
        } else {
            $baseQuery->where('projection_build_id', $activeBuildId);
        }

        $aggregate = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(canonical_cost_usd), 0) as canonical_cost_usd')
            ->selectRaw('COALESCE(SUM(provider_billed_cost_usd), 0) as provider_billed_cost_usd')
            ->first();

        $latestRollup = (clone $baseQuery)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first([
                'rate_card_version',
                'projection_build_id',
                'occurred_at',
            ]);

        $policy = DB::table('workflow_monthly_budget_policies')
            ->where('workflow_key', $workflowKey)
            ->where('is_active', true)
            ->first([
                'monthly_budget_usd',
                'warning_threshold_percent',
                'enforcement_threshold_percent',
            ]);

        $reliabilityRow = $this->activeBuildReliabilityRow($workflowKey, $activeBuildId);

        $governance = $governanceSnapshotService->snapshotForWorkflow(
            workflowKey: $workflowKey,
            reliabilityRow: $reliabilityRow,
            activeProjectionBuildId: $activeBuildId,
        );

        $monthlyCanonicalCostUsd = (float) ($aggregate->canonical_cost_usd ?? 0.0);
        $monthlyProviderBilledCostUsd = (float) ($aggregate->provider_billed_cost_usd ?? 0.0);
        $monthlyBudgetUsd = isset($policy->monthly_budget_usd) ? (float) $policy->monthly_budget_usd : null;

        $budgetUtilizationPercent = null;
        if ($monthlyBudgetUsd !== null && $monthlyBudgetUsd > 0) {
            $budgetUtilizationPercent = round(($monthlyCanonicalCostUsd / $monthlyBudgetUsd) * 100, 2);
        }

        return response()->json([
            'data' => [
                'workflow_key' => $workflowKey,
                'budget_month_utc' => $monthUtc,
                'monthly_canonical_cost_usd' => $monthlyCanonicalCostUsd,
                'monthly_provider_billed_cost_usd' => $monthlyProviderBilledCostUsd,
                'budget_utilization_percent' => $budgetUtilizationPercent,
                'monthly_budget_usd' => $monthlyBudgetUsd,
                'warning_threshold_percent' => isset($policy->warning_threshold_percent)
                    ? (float) $policy->warning_threshold_percent
                    : null,
                'enforcement_threshold_percent' => isset($policy->enforcement_threshold_percent)
                    ? (float) $policy->enforcement_threshold_percent
                    : null,
                'latest_rate_card_version' => isset($latestRollup->rate_card_version)
                    ? (string) $latestRollup->rate_card_version
                    : null,
                'active_projection_build_id' => $activeBuildId,
                'projection_build_id' => isset($latestRollup->projection_build_id)
                    ? (string) $latestRollup->projection_build_id
                    : $activeBuildId,
                'active_build_activated_at' => $freshness['active_build_activated_at'],
                'active_build_age_seconds' => $freshness['active_build_age_seconds'],
                'active_build_is_stale' => $freshness['active_build_is_stale'],
                'stale_after_seconds' => $freshness['stale_after_seconds'],
                'gate_state' => $governance['gate_state'],
                'gate_state_reason' => $governance['gate_state_reason'],
                'countability_state' => $governance['countability_state'],
                'countability_reason' => $governance['countability_reason'],
                'lineage' => array_merge($governance['lineage'], [
                    'cost_latest_occurred_at' => isset($latestRollup->occurred_at)
                        ? (string) $latestRollup->occurred_at
                        : null,
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeBuildReliabilityRow(string $workflowKey, ?string $activeBuildId): ?array
    {
        if ($activeBuildId === null) {
            return null;
        }

        $row = DB::table(ProjectionTable::qualified('workflow_reliability_current'))
            ->where('workflow_key', $workflowKey)
            ->where('projection_build_id', $activeBuildId)
            ->orderByDesc('updated_at')
            ->first([
                'workflow_key',
                'reliability_score',
                'degraded_rate',
                'hard_fail_rate',
                'projection_build_id',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'workflow_key' => (string) $row->workflow_key,
            'reliability_score' => isset($row->reliability_score) ? (float) $row->reliability_score : null,
            'degraded_rate' => isset($row->degraded_rate) ? (float) $row->degraded_rate : null,
            'hard_fail_rate' => isset($row->hard_fail_rate) ? (float) $row->hard_fail_rate : null,
            'projection_build_id' => (string) $row->projection_build_id,
        ];
    }
}
