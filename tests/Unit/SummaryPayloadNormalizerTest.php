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
}
