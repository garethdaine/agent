<?php

declare(strict_types=1);

namespace Tests\Feature\Escalation;

use App\Services\Escalation\DailyAlertSuppressionService;
use App\Services\Escalation\IncidentLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentUniquenessAndSuppressionTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_open_incident_is_unique_per_workflow_and_trigger_until_resolved(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T10:00:00+00:00'));

        $service = app(IncidentLifecycleService::class);

        $first = $service->openOrRefreshIncident(
            workflowKey: 'eng.repo-analysis.v1',
            triggerType: 'hard_fail_burst',
            reasonCode: 'hard_fail_burst'
        );

        $second = $service->openOrRefreshIncident(
            workflowKey: 'eng.repo-analysis.v1',
            triggerType: 'hard_fail_burst',
            reasonCode: 'hard_fail_burst'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table($this->projectionTable('escalation_incidents'))->count());

        $service->transitionIncident((int) $first->id, 'resolved');

        $third = $service->openOrRefreshIncident(
            workflowKey: 'eng.repo-analysis.v1',
            triggerType: 'hard_fail_burst',
            reasonCode: 'hard_fail_burst'
        );

        $this->assertNotSame($first->id, $third->id);
        $this->assertSame(2, DB::table($this->projectionTable('escalation_incidents'))->count());
    }

    public function test_alert_suppression_is_bucketed_by_utc_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-04T10:00:00+00:00'));

        $service = app(DailyAlertSuppressionService::class);

        $first = $service->suppressIfAlreadyAlertedToday('eng.code-implementation.v1', 'reliability_breach');
        $second = $service->suppressIfAlreadyAlertedToday('eng.code-implementation.v1', 'reliability_breach');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-05T01:00:00+00:00'));
        $third = $service->suppressIfAlreadyAlertedToday('eng.code-implementation.v1', 'reliability_breach');

        $this->assertFalse($first, 'First alert of UTC day should not be suppressed.');
        $this->assertTrue($second, 'Second alert in same UTC day should be suppressed.');
        $this->assertFalse($third, 'First alert in next UTC day should not be suppressed.');
    }

    private function projectionTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'agent_projection.'.$table
            : $table;
    }
}
