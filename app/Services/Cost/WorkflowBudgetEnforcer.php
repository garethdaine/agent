<?php

declare(strict_types=1);

namespace App\Services\Cost;

use App\Enums\GateTransitionSource;
use App\Models\AgentJob;
use App\Services\Escalation\IncidentLifecycleService;
use App\Services\Escalation\WorkflowGovernanceService;
use App\Services\Reliability\GateTransitionRecorder;
use App\Support\Telemetry\ProjectionTable;
use App\Support\Time\AgentClock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WorkflowBudgetEnforcer
{
    private const EVENT_WARNING = 'budget_warning';

    private const EVENT_BREACH = 'budget_breach';

    public function __construct(
        private readonly CanonicalCostCalculator $calculator,
        private readonly IncidentLifecycleService $incidentLifecycleService,
        private readonly WorkflowGovernanceService $workflowGovernanceService,
        private readonly GateTransitionRecorder $gateTransitionRecorder,
        private readonly AgentClock $clock,
    ) {}

    public function recordRunCost(
        AgentJob $job,
        string $runId,
        string $rateCardVersion,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?float $providerBilledCostUsd = null,
        ?string $projectionBuildId = null,
    ): WorkflowBudgetEnforcementResult {
        $workflowKey = (string) $job->workflow_key;
        $monthUtc = $this->clock->nowUtc()->format('Y-m');
        $policy = $this->loadPolicyForWorkflow($workflowKey);

        return DB::transaction(function () use (
            $job,
            $workflowKey,
            $runId,
            $rateCardVersion,
            $model,
            $inputTokens,
            $outputTokens,
            $providerBilledCostUsd,
            $projectionBuildId,
            $monthUtc,
            $policy,
        ): WorkflowBudgetEnforcementResult {
            $rollupsTable = $this->costRollupsTable();

            $existing = DB::table($rollupsTable)
                ->where('workflow_key', $workflowKey)
                ->where('run_id', $runId)
                ->lockForUpdate()
                ->first();

            $rollupCreated = false;
            $canonicalRunCostUsd = 0.0;
            $providerRunCostUsd = null;
            $pinnedRateCardVersion = $rateCardVersion;

            if ($existing !== null) {
                $canonicalRunCostUsd = (float) $existing->canonical_cost_usd;
                $providerRunCostUsd = $existing->provider_billed_cost_usd !== null
                    ? (float) $existing->provider_billed_cost_usd
                    : null;
                $pinnedRateCardVersion = (string) $existing->rate_card_version;
            } else {
                [$inputRate, $outputRate] = $this->resolveModelRates($rateCardVersion, $model);

                $canonicalRunCostUsd = $this->calculator->calculate(
                    inputTokens: $inputTokens,
                    outputTokens: $outputTokens,
                    inputCostPerThousandUsd: $inputRate,
                    outputCostPerThousandUsd: $outputRate,
                );
                $providerRunCostUsd = $providerBilledCostUsd !== null
                    ? round($providerBilledCostUsd, 6)
                    : null;

                $now = $this->clock->nowUtc();

                DB::table($rollupsTable)->insert([
                    'workflow_key' => $workflowKey,
                    'run_id' => $runId,
                    'budget_month_utc' => $monthUtc,
                    'rate_card_version' => $rateCardVersion,
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'canonical_cost_usd' => $canonicalRunCostUsd,
                    'provider_billed_cost_usd' => $providerRunCostUsd,
                    'projection_build_id' => $projectionBuildId,
                    'occurred_at' => $now,
                    'metadata_json' => json_encode([
                        'rate' => [
                            'input_cost_per_thousand_usd' => $inputRate,
                            'output_cost_per_thousand_usd' => $outputRate,
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $rollupCreated = true;
                $pinnedRateCardVersion = $rateCardVersion;
            }

            $aggregate = DB::table($rollupsTable)
                ->where('workflow_key', $workflowKey)
                ->where('budget_month_utc', $monthUtc)
                ->selectRaw('COALESCE(SUM(canonical_cost_usd), 0) as canonical_spend_usd')
                ->selectRaw('COALESCE(SUM(provider_billed_cost_usd), 0) as provider_spend_usd')
                ->first();

            $monthlyCanonicalSpendUsd = (float) ($aggregate?->canonical_spend_usd ?? 0.0); // @phpstan-ignore nullsafe.neverNull
            $monthlyProviderSpendUsd = (float) ($aggregate?->provider_spend_usd ?? 0.0); // @phpstan-ignore nullsafe.neverNull

            if ($policy === null) {
                return new WorkflowBudgetEnforcementResult(
                    thresholdStatus: 'unconfigured',
                    enforcementApplied: false,
                    canonicalRunCostUsd: $canonicalRunCostUsd,
                    providerBilledRunCostUsd: $providerRunCostUsd,
                    monthlyCanonicalSpendUsd: $monthlyCanonicalSpendUsd,
                    monthlyProviderBilledSpendUsd: $monthlyProviderSpendUsd,
                    utilizationPercent: null,
                    monthlyBudgetUsd: null,
                    warningThresholdPercent: null,
                    enforcementThresholdPercent: null,
                    pinnedRateCardVersion: $pinnedRateCardVersion,
                    rollupCreated: $rollupCreated,
                );
            }

            $monthlyBudgetUsd = (float) $policy['monthly_budget_usd'];
            $warningThresholdPercent = (float) $policy['warning_threshold_percent'];
            $enforcementThresholdPercent = (float) $policy['enforcement_threshold_percent'];

            $utilizationPercent = $this->calculateUtilizationPercent($monthlyCanonicalSpendUsd, $monthlyBudgetUsd);
            $thresholdStatus = $this->resolveThresholdStatus(
                utilizationPercent: $utilizationPercent,
                warningThresholdPercent: $warningThresholdPercent,
                enforcementThresholdPercent: $enforcementThresholdPercent,
            );

            if ($thresholdStatus === 'warning') {
                $this->emitBudgetEvent(
                    eventType: self::EVENT_WARNING,
                    workflowKey: $workflowKey,
                    runId: $runId,
                    monthUtc: $monthUtc,
                    utilizationPercent: $utilizationPercent,
                    monthlyCanonicalSpendUsd: $monthlyCanonicalSpendUsd,
                    monthlyProviderSpendUsd: $monthlyProviderSpendUsd,
                    projectionBuildId: $projectionBuildId,
                    policySnapshot: $this->policySnapshot($policy, $monthUtc),
                );
            }

            $enforcementApplied = false;

            if ($thresholdStatus === 'breach') {
                $policySnapshot = $this->policySnapshot($policy, $monthUtc);

                $this->emitBudgetEvent(
                    eventType: self::EVENT_BREACH,
                    workflowKey: $workflowKey,
                    runId: $runId,
                    monthUtc: $monthUtc,
                    utilizationPercent: $utilizationPercent,
                    monthlyCanonicalSpendUsd: $monthlyCanonicalSpendUsd,
                    monthlyProviderSpendUsd: $monthlyProviderSpendUsd,
                    projectionBuildId: $projectionBuildId,
                    policySnapshot: $policySnapshot,
                );

                $this->incidentLifecycleService->openOrRefreshIncident(
                    workflowKey: $workflowKey,
                    triggerType: self::EVENT_BREACH,
                    reasonCode: self::EVENT_BREACH,
                    reason: sprintf(
                        'Workflow monthly budget breach detected for %s: %.2f / %.2f USD (%.2f%%).',
                        $monthUtc,
                        $monthlyCanonicalSpendUsd,
                        $monthlyBudgetUsd,
                        $utilizationPercent
                    ),
                    projectionBuildId: $projectionBuildId,
                    metadata: [
                        'policy_snapshot' => $policySnapshot,
                        'canonical_monthly_spend_usd' => $monthlyCanonicalSpendUsd,
                        'provider_monthly_spend_usd' => $monthlyProviderSpendUsd,
                        'utilization_percent' => $utilizationPercent,
                    ]
                );

                $job->refresh();

                if ($job->governance_paused_at === null) {
                    $this->workflowGovernanceService->pauseWorkflow(
                        job: $job,
                        actorId: (string) config('agent.cost_governance.system_actor_id', 'system:budget-enforcer'),
                        reason: sprintf(
                            'Monthly budget breached (%s %.2f/%.2f USD, %.2f%%).',
                            $monthUtc,
                            $monthlyCanonicalSpendUsd,
                            $monthlyBudgetUsd,
                            $utilizationPercent
                        ),
                        runId: $runId,
                        force: false,
                    );

                    $this->gateTransitionRecorder->record(
                        workflowKey: $workflowKey,
                        previousGateState: 'active',
                        newGateState: 'paused',
                        source: GateTransitionSource::BUDGET_ENFORCEMENT,
                        reasonCode: self::EVENT_BREACH,
                        reason: 'Monthly budget enforcement threshold reached.',
                        actorId: (string) config('agent.cost_governance.system_actor_id', 'system:budget-enforcer'),
                        runId: $runId,
                        projectionBuildId: $projectionBuildId,
                        metadata: [
                            'policy_snapshot' => $policySnapshot,
                            'canonical_monthly_spend_usd' => $monthlyCanonicalSpendUsd,
                            'utilization_percent' => $utilizationPercent,
                        ],
                    );
                }

                $enforcementApplied = true;
            }

            return new WorkflowBudgetEnforcementResult(
                thresholdStatus: $thresholdStatus,
                enforcementApplied: $enforcementApplied,
                canonicalRunCostUsd: $canonicalRunCostUsd,
                providerBilledRunCostUsd: $providerRunCostUsd,
                monthlyCanonicalSpendUsd: $monthlyCanonicalSpendUsd,
                monthlyProviderBilledSpendUsd: $monthlyProviderSpendUsd,
                utilizationPercent: $utilizationPercent,
                monthlyBudgetUsd: $monthlyBudgetUsd,
                warningThresholdPercent: $warningThresholdPercent,
                enforcementThresholdPercent: $enforcementThresholdPercent,
                pinnedRateCardVersion: $pinnedRateCardVersion,
                rollupCreated: $rollupCreated,
            );
        });
    }

    /**
     * @return array{monthly_budget_usd:float,warning_threshold_percent:float,enforcement_threshold_percent:float,is_active:bool}|null
     */
    private function loadPolicyForWorkflow(string $workflowKey): ?array
    {
        $row = DB::table('workflow_monthly_budget_policies')
            ->where('workflow_key', $workflowKey)
            ->where('is_active', true)
            ->first([
                'monthly_budget_usd',
                'warning_threshold_percent',
                'enforcement_threshold_percent',
                'is_active',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'monthly_budget_usd' => (float) $row->monthly_budget_usd,
            'warning_threshold_percent' => (float) $row->warning_threshold_percent,
            'enforcement_threshold_percent' => (float) $row->enforcement_threshold_percent,
            'is_active' => (bool) $row->is_active,
        ];
    }

    /**
     * @return array{0:float,1:float}
     */
    private function resolveModelRates(string $rateCardVersion, string $model): array
    {
        $cards = config('agent.cost_governance.rate_cards', []);
        if (! is_array($cards) || ! isset($cards[$rateCardVersion]) || ! is_array($cards[$rateCardVersion])) {
            throw new RuntimeException(sprintf('Rate card version "%s" is not configured.', $rateCardVersion));
        }

        $models = $cards[$rateCardVersion]['models'] ?? null;
        if (! is_array($models)) {
            throw new RuntimeException(sprintf('Rate card version "%s" has no model rates.', $rateCardVersion));
        }

        $modelRates = $models[$model] ?? $models['*'] ?? null;
        if (! is_array($modelRates)) {
            throw new RuntimeException(sprintf(
                'Rate card version "%s" has no model rates for "%s".',
                $rateCardVersion,
                $model
            ));
        }

        $inputRate = $modelRates['input_cost_per_thousand_usd'] ?? null;
        $outputRate = $modelRates['output_cost_per_thousand_usd'] ?? null;

        if (! is_numeric($inputRate) || ! is_numeric($outputRate)) {
            throw new RuntimeException(sprintf(
                'Rate card "%s" model "%s" must define numeric input/output rates.',
                $rateCardVersion,
                $model
            ));
        }

        return [(float) $inputRate, (float) $outputRate];
    }

    /**
     * @param  array{monthly_budget_usd:float,warning_threshold_percent:float,enforcement_threshold_percent:float,is_active:bool}  $policy
     * @return array<string, mixed>
     */
    private function policySnapshot(array $policy, string $monthUtc): array
    {
        return [
            'month_utc' => $monthUtc,
            'monthly_budget_usd' => (float) $policy['monthly_budget_usd'],
            'warning_threshold_percent' => (float) $policy['warning_threshold_percent'],
            'enforcement_threshold_percent' => (float) $policy['enforcement_threshold_percent'],
            'is_active' => (bool) $policy['is_active'],
        ];
    }

    /**
     * @param  array<string, mixed>  $policySnapshot
     */
    private function emitBudgetEvent(
        string $eventType,
        string $workflowKey,
        string $runId,
        string $monthUtc,
        float $utilizationPercent,
        float $monthlyCanonicalSpendUsd,
        float $monthlyProviderSpendUsd,
        ?string $projectionBuildId,
        array $policySnapshot,
    ): void {
        $table = $this->budgetEventsTable();
        $now = $this->clock->nowUtc();

        DB::table($table)->updateOrInsert(
            [
                'workflow_key' => $workflowKey,
                'budget_month_utc' => $monthUtc,
                'event_type' => $eventType,
                'run_id' => $runId,
            ],
            [
                'triggered_at' => $now,
                'policy_snapshot_json' => json_encode($policySnapshot, JSON_THROW_ON_ERROR),
                'utilization_percent' => round($utilizationPercent, 2),
                'canonical_monthly_spend_usd' => round($monthlyCanonicalSpendUsd, 6),
                'provider_monthly_spend_usd' => round($monthlyProviderSpendUsd, 6),
                'projection_build_id' => $projectionBuildId,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function calculateUtilizationPercent(float $monthlyCanonicalSpendUsd, float $monthlyBudgetUsd): float
    {
        if ($monthlyBudgetUsd <= 0.0) {
            return $monthlyCanonicalSpendUsd > 0 ? 100.0 : 0.0;
        }

        return round(($monthlyCanonicalSpendUsd / $monthlyBudgetUsd) * 100, 2);
    }

    private function resolveThresholdStatus(
        float $utilizationPercent,
        float $warningThresholdPercent,
        float $enforcementThresholdPercent,
    ): string {
        if ($utilizationPercent >= $enforcementThresholdPercent) {
            return 'breach';
        }

        if ($utilizationPercent >= $warningThresholdPercent) {
            return 'warning';
        }

        return 'normal';
    }

    private function costRollupsTable(): string
    {
        return ProjectionTable::qualified('workflow_cost_rollups');
    }

    private function budgetEventsTable(): string
    {
        return ProjectionTable::qualified('workflow_budget_events');
    }
}
