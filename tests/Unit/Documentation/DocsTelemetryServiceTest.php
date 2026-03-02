<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Events\Documentation\DocsSearchUnavailableDetected;
use App\Events\Documentation\DocsSyncOutcomeRecorded;
use App\Events\Documentation\TooltipKeyMissingDetected;
use App\Support\Documentation\DocsTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class DocsTelemetryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_key_records_counter_and_dispatches_deduplicated_event(): void
    {
        Event::fake([TooltipKeyMissingDetected::class]);

        $service = app(DocsTelemetryService::class);

        $service->recordTooltipMiss('jobs.timeout.warning', 'missing_key', [
            'source' => 'api',
            'route_name' => 'agent.jobs.index',
        ]);

        $service->recordTooltipMiss('jobs.timeout.warning', 'missing_key', [
            'source' => 'api',
            'route_name' => 'agent.jobs.index',
        ]);

        Event::assertDispatchedTimes(TooltipKeyMissingDetected::class, 1);

        $snapshot = $service->snapshot();

        $this->assertSame(2, $snapshot['counters']['tooltip_missing_key_total']);
    }

    public function test_search_unavailable_dispatches_event_and_increments_counter(): void
    {
        Event::fake([DocsSearchUnavailableDetected::class]);

        $service = app(DocsTelemetryService::class);

        $service->recordSearchUnavailable(
            query: 'agent jobs',
            routeName: 'agent.jobs.index',
            throwable: new RuntimeException('typesense unavailable'),
            context: ['source' => 'api']
        );

        Event::assertDispatched(DocsSearchUnavailableDetected::class, function (DocsSearchUnavailableDetected $event): bool {
            return $event->routeName === 'agent.jobs.index'
                && $event->errorClass === RuntimeException::class
                && $event->queryHash !== '';
        });

        $snapshot = $service->snapshot();

        $this->assertSame(1, $snapshot['counters']['docs_search_unavailable_total']);
    }

    public function test_sync_outcomes_increment_success_and_failure_counters(): void
    {
        Event::fake([DocsSyncOutcomeRecorded::class]);

        $service = app(DocsTelemetryService::class);

        $service->recordSyncOutcome(
            mode: 'commit',
            source: 'repo',
            success: true,
            summary: [
                'entries_count' => 2,
                'fragments_count' => 1,
                'links_count' => 4,
            ]
        );

        $service->recordSyncOutcome(
            mode: 'commit',
            source: 'repo',
            success: false,
            summary: [
                'entries_count' => 0,
                'fragments_count' => 0,
                'links_count' => 0,
            ],
            errorCode: 'DOCS_VALIDATION_FAILED',
            errorMessage: 'front matter missing field'
        );

        Event::assertDispatchedTimes(DocsSyncOutcomeRecorded::class, 2);

        $snapshot = $service->snapshot();

        $this->assertSame(1, $snapshot['counters']['docs_sync_success_total']);
        $this->assertSame(1, $snapshot['counters']['docs_sync_failure_total']);
    }
}
