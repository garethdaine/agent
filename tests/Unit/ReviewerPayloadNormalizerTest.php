<?php

namespace Tests\Unit;

use App\Support\Interrogation\ReviewerPayloadNormalizer;
use Tests\TestCase;

class ReviewerPayloadNormalizerTest extends TestCase
{
    public function test_severity_normalized_to_lowercase(): void
    {
        $payload = [
            'verdict' => 'revise',
            'issues' => [
                ['type' => 'contradiction', 'severity' => 'HIGH', 'message' => 'Test', 'evidence' => 'x'],
                ['type' => 'ambiguity', 'severity' => 'Critical', 'message' => 'Test2', 'evidence' => 'y'],
            ],
            'confidence' => 0.5,
        ];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertSame('high', $result['issues'][0]['severity']);
        $this->assertSame('critical', $result['issues'][1]['severity']);
    }

    public function test_empty_strings_filtered_from_required_changes(): void
    {
        $payload = [
            'verdict' => 'revise',
            'issues' => [],
            'required_changes' => ['Add auth', '', '  ', 'Fix scope'],
            'confidence' => 0.5,
        ];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertCount(2, $result['required_changes']);
        $this->assertSame(['Add auth', 'Fix scope'], array_values($result['required_changes']));
    }

    public function test_clarification_questions_capped_at_3(): void
    {
        $payload = [
            'verdict' => 'needs_clarification',
            'issues' => [],
            'clarification_questions' => ['Q1', 'Q2', 'Q3', 'Q4', 'Q5'],
            'confidence' => 0.5,
        ];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertCount(3, $result['clarification_questions']);
        $this->assertSame(['Q1', 'Q2', 'Q3'], $result['clarification_questions']);
    }

    public function test_confidence_clamped_below_zero(): void
    {
        $payload = ['verdict' => 'pass', 'issues' => [], 'confidence' => -0.5];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_confidence_clamped_above_one(): void
    {
        $payload = ['verdict' => 'pass', 'issues' => [], 'confidence' => 1.8];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertSame(1.0, $result['confidence']);
    }

    public function test_missing_optional_fields_get_defaults(): void
    {
        $payload = ['verdict' => 'pass'];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertSame([], $result['issues']);
        $this->assertSame([], $result['required_changes']);
        $this->assertSame([], $result['clarification_questions']);
        $this->assertSame(1.0, $result['confidence']);
        $this->assertSame('', $result['review_notes']);
    }

    public function test_verdict_normalized_to_lowercase(): void
    {
        $payload = ['verdict' => 'PASS', 'issues' => [], 'confidence' => 0.9];

        $normalizer = new ReviewerPayloadNormalizer;
        $result = $normalizer->normalize($payload);

        $this->assertSame('pass', $result['verdict']);
    }
}
