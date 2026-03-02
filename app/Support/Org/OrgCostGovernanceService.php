<?php

namespace App\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgCostLedger;
use App\Models\OrgRitualRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrgCostGovernanceService
{
    /**
     * Record a cost entry for a ritual run, optionally at the agent level.
     */
    public function recordCost(OrgRitualRun $run, ?OrgAgentProfile $agent, array $data): OrgCostLedger
    {
        return OrgCostLedger::create([
            'user_id' => $run->user_id,
            'org_agent_id' => $agent?->id,
            'ritual_run_id' => $run->id,
            'token_count' => $data['token_count'] ?? 0,
            'runtime_ms' => $data['runtime_ms'] ?? 0,
            'estimated_cost_usd' => $data['estimated_cost_usd'] ?? 0.0,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Get aggregated costs for a specific ritual run across all agents.
     */
    public function getRitualRunCosts(OrgRitualRun $run): CostSummary
    {
        $aggregate = OrgCostLedger::forRun($run->id)
            ->selectRaw('
                COALESCE(SUM(token_count), 0) as total_tokens,
                COALESCE(SUM(runtime_ms), 0) as total_runtime_ms,
                COALESCE(SUM(estimated_cost_usd), 0) as total_cost_usd,
                COUNT(*) as entry_count
            ')
            ->first();

        if (! $aggregate) {
            return CostSummary::zero();
        }

        return CostSummary::fromAggregate($aggregate->toArray());
    }

    /**
     * Get aggregated costs for a specific agent within a date range.
     */
    public function getAgentCosts(User $user, OrgAgentProfile $agent, $startDate, $endDate): CostSummary
    {
        $aggregate = OrgCostLedger::forUser($user->id)
            ->forAgent($agent->id)
            ->recordedBetween($startDate, $endDate)
            ->selectRaw('
                COALESCE(SUM(token_count), 0) as total_tokens,
                COALESCE(SUM(runtime_ms), 0) as total_runtime_ms,
                COALESCE(SUM(estimated_cost_usd), 0) as total_cost_usd,
                COUNT(*) as entry_count
            ')
            ->first();

        if (! $aggregate) {
            return CostSummary::zero();
        }

        return CostSummary::fromAggregate($aggregate->toArray());
    }

    /**
     * Get aggregated costs for a user across all agents and runs within a date range.
     */
    public function getUserCostSummary(User $user, $startDate, $endDate): CostSummary
    {
        $aggregate = OrgCostLedger::forUser($user->id)
            ->recordedBetween($startDate, $endDate)
            ->selectRaw('
                COALESCE(SUM(token_count), 0) as total_tokens,
                COALESCE(SUM(runtime_ms), 0) as total_runtime_ms,
                COALESCE(SUM(estimated_cost_usd), 0) as total_cost_usd,
                COUNT(*) as entry_count
            ')
            ->first();

        if (! $aggregate) {
            return CostSummary::zero();
        }

        return CostSummary::fromAggregate($aggregate->toArray());
    }

    /**
     * Get per-agent cost breakdown for a user within a date range.
     */
    public function getPerAgentCostBreakdown(User $user, $startDate, $endDate): array
    {
        return OrgCostLedger::forUser($user->id)
            ->recordedBetween($startDate, $endDate)
            ->whereNotNull('org_agent_id')
            ->selectRaw('
                org_agent_id,
                SUM(token_count) as total_tokens,
                SUM(runtime_ms) as total_runtime_ms,
                SUM(estimated_cost_usd) as total_cost_usd,
                COUNT(*) as entry_count
            ')
            ->groupBy('org_agent_id')
            ->get()
            ->map(fn ($row) => [
                'agent_id' => $row->org_agent_id,
                'summary' => CostSummary::fromAggregate($row->toArray()),
            ])
            ->toArray();
    }

    /**
     * Get per-ritual-run cost breakdown for a user within a date range.
     */
    public function getPerRitualRunCostBreakdown(User $user, $startDate, $endDate): array
    {
        return OrgCostLedger::forUser($user->id)
            ->recordedBetween($startDate, $endDate)
            ->whereNotNull('ritual_run_id')
            ->selectRaw('
                ritual_run_id,
                SUM(token_count) as total_tokens,
                SUM(runtime_ms) as total_runtime_ms,
                SUM(estimated_cost_usd) as total_cost_usd,
                COUNT(*) as entry_count
            ')
            ->groupBy('ritual_run_id')
            ->get()
            ->map(fn ($row) => [
                'ritual_run_id' => $row->ritual_run_id,
                'summary' => CostSummary::fromAggregate($row->toArray()),
            ])
            ->toArray();
    }

    /**
     * Enforce hard stop behavior based on configuration.
     * Per requirements: block undispatched branches only, let running branches complete.
     */
    public function enforceHardStop(OrgRitualRun $run): HardStopResult
    {
        $behavior = config('agent.org.budget_hard_stop_behavior', 'block_undispatched');

        return match ($behavior) {
            'block_undispatched' => HardStopResult::blockUndispatched(
                reason: 'Budget threshold exceeded - blocking new branches while allowing running branches to complete'
            ),
            'terminate_all' => HardStopResult::terminateAll(
                reason: 'Budget threshold exceeded - terminating all branches'
            ),
            default => HardStopResult::none(),
        };
    }

    /**
     * Evaluate cost threshold status for a user.
     * Returns threshold level: 'normal', 'warning', 'approval_required', or 'hard_stop'.
     */
    public function evaluateThreshold(User $user, CostSummary $costs): string
    {
        // TODO: Implement configurable threshold evaluation based on user settings
        // For now, return normal as we don't have threshold configuration yet
        return 'normal';
    }
}
