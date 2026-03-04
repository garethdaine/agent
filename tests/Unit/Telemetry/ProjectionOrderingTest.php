<?php

declare(strict_types=1);

namespace Tests\Unit\Telemetry;

use App\Services\Telemetry\ProjectionOrderingService;
use Tests\TestCase;

class ProjectionOrderingTest extends TestCase
{
    public function test_ordering_is_deterministic_for_same_input_set(): void
    {
        $service = new ProjectionOrderingService;

        $events = [
            [
                'id' => 30,
                'event_id' => 'evt-c',
                'event_sequence' => 3,
                'ingested_at' => '2026-03-04T10:00:03Z',
                'event_at' => '2026-03-04T10:00:03Z',
            ],
            [
                'id' => 20,
                'event_id' => 'evt-b',
                'event_sequence' => 2,
                'ingested_at' => '2026-03-04T10:00:02Z',
                'event_at' => '2026-03-04T10:00:02Z',
            ],
            [
                'id' => 10,
                'event_id' => 'evt-a',
                'event_sequence' => 1,
                'ingested_at' => '2026-03-04T10:00:01Z',
                'event_at' => '2026-03-04T10:00:01Z',
            ],
        ];

        $orderedA = $service->orderForProjection($events);
        $orderedB = $service->orderForProjection(array_reverse($events));

        $this->assertSame([10, 20, 30], array_column($orderedA, 'id'));
        $this->assertSame([10, 20, 30], array_column($orderedB, 'id'));
    }

    public function test_tie_breakers_are_stable_when_sequence_matches(): void
    {
        $service = new ProjectionOrderingService;

        $events = [
            [
                'id' => 102,
                'event_id' => 'evt-z',
                'event_sequence' => 5,
                'ingested_at' => '2026-03-04T10:00:05Z',
                'event_at' => '2026-03-04T10:00:05Z',
            ],
            [
                'id' => 101,
                'event_id' => 'evt-y',
                'event_sequence' => 5,
                'ingested_at' => '2026-03-04T10:00:04Z',
                'event_at' => '2026-03-04T10:00:05Z',
            ],
            [
                'id' => 100,
                'event_id' => 'evt-x',
                'event_sequence' => 5,
                'ingested_at' => '2026-03-04T10:00:04Z',
                'event_at' => '2026-03-04T10:00:04Z',
            ],
        ];

        $ordered = $service->orderForProjection($events);

        $this->assertSame([100, 101, 102], array_column($ordered, 'id'));
    }
}
