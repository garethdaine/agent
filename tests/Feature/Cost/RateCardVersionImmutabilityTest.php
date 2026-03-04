<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\AgentJob;
use App\Models\User;
use App\Services\Cost\WorkflowBudgetEnforcer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RateCardVersionImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('agent.cost_governance.rate_cards', [
            'rc-2026-03' => [
                'models' => [
                    'gpt-5' => [
                        'input_cost_per_thousand_usd' => 1.0,
                        'output_cost_per_thousand_usd' => 1.0,
                    ],
                ],
            ],
            'rc-2026-04' => [
                'models' => [
                    'gpt-5' => [
                        'input_cost_per_thousand_usd' => 20.0,
                        'output_cost_per_thousand_usd' => 20.0,
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

    public function test_rate_card_version_is_pinned_and_immutable_for_existing_run_rollup(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T18:00:00+00:00'));

        $user = User::factory()->create();
        $job = AgentJob::factory()->create([
            'user_id' => $user->id,
            'workflow_key' => 'eng.release-readiness.v1',
        ]);

        DB::table('workflow_monthly_budget_policies')->insert([
            'workflow_key' => $job->workflow_key,
            'monthly_budget_usd' => 1000.0,
            'warning_threshold_percent' => 80.0,
            'enforcement_threshold_percent' => 100.0,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $service = app(WorkflowBudgetEnforcer::class);

        $first = $service->recordRunCost(
            job: $job,
            runId: 'run-immutable-1',
            rateCardVersion: 'rc-2026-03',
            model: 'gpt-5',
            inputTokens: 2000,
            outputTokens: 0,
            providerBilledCostUsd: 1.0,
        );

        $second = $service->recordRunCost(
            job: $job,
            runId: 'run-immutable-1',
            rateCardVersion: 'rc-2026-04',
            model: 'gpt-5',
            inputTokens: 9000,
            outputTokens: 0,
            providerBilledCostUsd: 99.0,
        );

        $this->assertSame('rc-2026-03', $first->pinnedRateCardVersion);
        $this->assertSame('rc-2026-03', $second->pinnedRateCardVersion);
        $this->assertSame(2.0, $first->canonicalRunCostUsd);
        $this->assertSame(2.0, $second->canonicalRunCostUsd);

        $rows = DB::table($this->projectionTable('workflow_cost_rollups'))
            ->where('workflow_key', $job->workflow_key)
            ->where('run_id', 'run-immutable-1')
            ->get();

        $this->assertCount(1, $rows);

        $row = $rows->first();
        $this->assertNotNull($row);
        $this->assertSame('rc-2026-03', (string) $row->rate_card_version);
        $this->assertSame(2.0, (float) $row->canonical_cost_usd);
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
