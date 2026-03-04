<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use App\Services\Cost\WorkflowBudgetEnforcer;
use App\Support\Agent\DispatchDueService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agent.cost_governance.rate_cards', [
            'rc-2026-03' => [
                'models' => [
                    'gpt-5' => [
                        'input_cost_per_thousand_usd' => 2.0,
                        'output_cost_per_thousand_usd' => 2.0,
                    ],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_emits_warning_at_eighty_percent_without_pausing_workflow(): void
    {
        $now = CarbonImmutable::parse('2026-03-04T12:00:00+00:00');
        CarbonImmutable::setTestNow($now);

        $user = User::factory()->create();
        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
            'workflow_key' => 'eng.repo-analysis.v1',
        ]);

        $this->insertPolicy($job->workflow_key, monthlyBudgetUsd: 10.0);

        $result = app(WorkflowBudgetEnforcer::class)->recordRunCost(
            job: $job,
            runId: 'run-budget-warning-1',
            rateCardVersion: 'rc-2026-03',
            model: 'gpt-5',
            inputTokens: 4000,
            outputTokens: 0,
            providerBilledCostUsd: 2.0,
        );

        $job->refresh();

        $this->assertSame('warning', $result->thresholdStatus);
        $this->assertFalse($result->enforcementApplied);
        $this->assertNull($job->governance_paused_at);

        $event = DB::table($this->projectionTable('workflow_budget_events'))
            ->where('workflow_key', $job->workflow_key)
            ->where('event_type', 'budget_warning')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('run-budget-warning-1', $event->run_id);

        $incidentCount = DB::table($this->projectionTable('escalation_incidents'))
            ->where('workflow_key', $job->workflow_key)
            ->where('trigger_type', 'budget_breach')
            ->count();

        $this->assertSame(0, $incidentCount);
    }

    public function test_it_blocks_new_runs_at_hundred_percent_while_inflight_runs_continue(): void
    {
        Queue::fake();

        $now = CarbonImmutable::parse('2026-03-04T14:05:00+00:00')->startOfMinute();
        CarbonImmutable::setTestNow($now);

        $user = User::factory()->create();

        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
            'workflow_key' => 'eng.code-implementation.v1',
            'cron_expression' => sprintf('%d %d %d %d %d', $now->minute, $now->hour, $now->day, $now->month, $now->dayOfWeek),
            'timezone' => 'UTC',
            'is_enabled' => true,
            'cooldown_seconds' => 0,
        ]);

        $running = AgentJobRun::factory()->running()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);

        $this->insertPolicy($job->workflow_key, monthlyBudgetUsd: 10.0);

        $result = app(WorkflowBudgetEnforcer::class)->recordRunCost(
            job: $job,
            runId: 'run-budget-breach-1',
            rateCardVersion: 'rc-2026-03',
            model: 'gpt-5',
            inputTokens: 5000,
            outputTokens: 0,
            providerBilledCostUsd: 1.5,
        );

        $job->refresh();
        $running->refresh();

        $this->assertSame('breach', $result->thresholdStatus);
        $this->assertTrue($result->enforcementApplied);
        $this->assertNotNull($job->governance_paused_at);
        $this->assertSame(AgentJobRun::STATUS_RUNNING, $running->status);

        app(DispatchDueService::class)->dispatch($now);

        $skipped = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->where('status', AgentJobRun::STATUS_SKIPPED)
            ->latest('id')
            ->first();

        $this->assertNotNull($skipped);
        $this->assertSame('governance_paused', data_get($skipped?->metadata_json, 'skip_reason'));

        Sanctum::actingAs($user);

        $this->postJson('/agent/api/v1/jobs/'.$job->id.'/run-now')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'WORKFLOW_GOVERNANCE_PAUSED');

        $incident = DB::table($this->projectionTable('escalation_incidents'))
            ->where('workflow_key', $job->workflow_key)
            ->where('trigger_type', 'budget_breach')
            ->latest('id')
            ->first();

        $this->assertNotNull($incident);
        $this->assertSame('budget_breach', $incident->reason_code);

        $metadata = is_string($incident->metadata_json)
            ? json_decode($incident->metadata_json, true, 512, JSON_THROW_ON_ERROR)
            : (array) $incident->metadata_json;

        $this->assertSame(10.0, (float) data_get($metadata, 'policy_snapshot.monthly_budget_usd'));
    }

    public function test_provider_billed_cost_is_stored_but_does_not_drive_enforcement(): void
    {
        $now = CarbonImmutable::parse('2026-03-04T16:00:00+00:00');
        CarbonImmutable::setTestNow($now);

        $user = User::factory()->create();
        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
            'workflow_key' => 'eng.pr-quality-gate.v1',
        ]);

        $this->insertPolicy($job->workflow_key, monthlyBudgetUsd: 10.0);

        $result = app(WorkflowBudgetEnforcer::class)->recordRunCost(
            job: $job,
            runId: 'run-provider-billed-1',
            rateCardVersion: 'rc-2026-03',
            model: 'gpt-5',
            inputTokens: 3500,
            outputTokens: 0,
            providerBilledCostUsd: 15.0,
        );

        $this->assertSame('normal', $result->thresholdStatus);
        $this->assertFalse($result->enforcementApplied);

        $rollup = DB::table($this->projectionTable('workflow_cost_rollups'))
            ->where('workflow_key', $job->workflow_key)
            ->where('run_id', 'run-provider-billed-1')
            ->first();

        $this->assertNotNull($rollup);
        $this->assertSame(7.0, (float) $rollup->canonical_cost_usd);
        $this->assertSame(15.0, (float) $rollup->provider_billed_cost_usd);
    }

    private function insertPolicy(string $workflowKey, float $monthlyBudgetUsd): void
    {
        DB::table('workflow_monthly_budget_policies')->insert([
            'workflow_key' => $workflowKey,
            'monthly_budget_usd' => $monthlyBudgetUsd,
            'warning_threshold_percent' => 80.0,
            'enforcement_threshold_percent' => 100.0,
            'is_active' => true,
            'metadata_json' => json_encode(['owner' => 'platform'], JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
