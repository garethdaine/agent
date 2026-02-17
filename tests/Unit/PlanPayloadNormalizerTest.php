<?php

namespace Tests\Unit;

use App\Support\Interrogation\PlanPayloadNormalizer;
use Tests\TestCase;

class PlanPayloadNormalizerTest extends TestCase
{
    public function test_it_removes_estimate_and_timeline_content_from_plan_payload(): void
    {
        $normalizer = new PlanPayloadNormalizer;

        $payload = [
            'plan_markdown' => <<<'MD'
## Implementation Steps
- Build data model.

## Total Estimated Effort
- Phases 1-8: 21-29 working days (~4-6 weeks for 1 developer)
- Critical path: Phase 1 -> 2 -> 3

## Rollout
- Deploy behind feature flag.
MD,
            'sections' => ['Implementation Steps', 'Total Estimated Effort'],
            'risks' => ['Timeline may slip with fewer engineers', 'Rate limits on provider API'],
            'assumptions' => ['One team can deliver in 3 weeks', 'Primary DB already provisioned'],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertStringContainsString('## Implementation Steps', $normalized['plan_markdown']);
        $this->assertStringContainsString('## Rollout', $normalized['plan_markdown']);
        $this->assertStringNotContainsString('Total Estimated Effort', $normalized['plan_markdown']);
        $this->assertStringNotContainsString('Critical path', $normalized['plan_markdown']);
        $this->assertStringNotContainsString('working days', $normalized['plan_markdown']);

        $this->assertSame(['Implementation Steps'], $normalized['sections']);
        $this->assertSame(['Rate limits on provider API'], $normalized['risks']);
        $this->assertSame(['Primary DB already provisioned'], $normalized['assumptions']);
    }

    public function test_it_keeps_non_estimate_operational_durations(): void
    {
        $normalizer = new PlanPayloadNormalizer;

        $payload = [
            'plan_markdown' => "## Runtime Config\n- Set cache TTL to 7 days.\n- Run compaction every 24 hours.",
            'sections' => ['Runtime Config'],
            'risks' => [],
            'assumptions' => [],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertStringContainsString('Set cache TTL to 7 days.', $normalized['plan_markdown']);
        $this->assertStringContainsString('Run compaction every 24 hours.', $normalized['plan_markdown']);
        $this->assertSame(['Runtime Config'], $normalized['sections']);
    }
}
