<?php

namespace Tests\Unit;

use App\Support\Interrogation\SummaryPayloadNormalizer;
use Tests\TestCase;

class SummaryPayloadNormalizerTest extends TestCase
{
    public function test_it_extracts_embedded_parameter_lists_from_summary_markdown(): void
    {
        $normalizer = new SummaryPayloadNormalizer;

        $payload = [
            'summary_markdown' => "## Summary\n\nService row text.\n<parameter name=\"goals\">[\"Goal A\",\"Goal B\"]\n\nMore text.",
            'goals' => [],
            'constraints' => [],
            'acceptance_criteria' => [],
            'open_questions' => [],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertSame(['Goal A', 'Goal B'], $normalized['goals']);
        $this->assertStringNotContainsString('<parameter name="goals">', $normalized['summary_markdown']);
        $this->assertStringContainsString('## Summary', $normalized['summary_markdown']);
        $this->assertStringContainsString('More text.', $normalized['summary_markdown']);
    }

    public function test_it_keeps_explicit_list_values_when_already_present(): void
    {
        $normalizer = new SummaryPayloadNormalizer;

        $payload = [
            'summary_markdown' => '<parameter name="goals">["Parsed Goal"]',
            'goals' => ['Explicit Goal'],
            'constraints' => [],
            'acceptance_criteria' => [],
            'open_questions' => [],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertSame(['Explicit Goal'], $normalized['goals']);
        $this->assertStringNotContainsString('<parameter name="goals">', $normalized['summary_markdown']);
    }

    public function test_it_removes_estimate_and_timeline_content_from_summary_payload(): void
    {
        $normalizer = new SummaryPayloadNormalizer;

        $payload = [
            'summary_markdown' => <<<'MD'
## Scope
- Build delegation memory layers.

## Estimated Timeline
- Estimate: 3-4 days | Prerequisite: None | Risk: Low
- Critical path: Storage -> Retrieval -> Delegation

## Decisions
- Use Laravel AI SDK.
MD,
            'goals' => ['Ship MVP memory stack'],
            'constraints' => ['Timeline target is 2 weeks for one developer', 'SOC2 compliance required'],
            'acceptance_criteria' => ['Delivery within 5 days', 'All endpoints return 2xx'],
            'open_questions' => ['What is expected daily run volume?', 'Can one engineer deliver this in 3 weeks?'],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertStringContainsString('## Scope', $normalized['summary_markdown']);
        $this->assertStringContainsString('## Decisions', $normalized['summary_markdown']);
        $this->assertStringNotContainsString('Estimated Timeline', $normalized['summary_markdown']);
        $this->assertStringNotContainsString('Estimate: 3-4 days', $normalized['summary_markdown']);
        $this->assertStringNotContainsString('Critical path', $normalized['summary_markdown']);

        $this->assertSame(['SOC2 compliance required'], $normalized['constraints']);
        $this->assertSame(['All endpoints return 2xx'], $normalized['acceptance_criteria']);
        $this->assertSame(['What is expected daily run volume?'], $normalized['open_questions']);
    }

    public function test_it_keeps_operational_duration_config_in_summary_markdown(): void
    {
        $normalizer = new SummaryPayloadNormalizer;

        $payload = [
            'summary_markdown' => "## Runtime\n- Working memory TTL is 7 days.\n- Consolidation runs every 24 hours.",
            'goals' => [],
            'constraints' => [],
            'acceptance_criteria' => [],
            'open_questions' => [],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertStringContainsString('TTL is 7 days', $normalized['summary_markdown']);
        $this->assertStringContainsString('every 24 hours', $normalized['summary_markdown']);
    }

    public function test_it_removes_non_actionable_operational_open_questions(): void
    {
        $normalizer = new SummaryPayloadNormalizer;

        $payload = [
            'summary_markdown' => 'Summary content',
            'goals' => [],
            'constraints' => [],
            'acceptance_criteria' => [],
            'open_questions' => [
                'Please paste the exact full text (verbatim) of the latest resumed interrogation question that is still unanswered.',
                'Which authentication mode should the MVP support first?',
            ],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertSame(
            ['Which authentication mode should the MVP support first?'],
            $normalized['open_questions']
        );
    }
}
