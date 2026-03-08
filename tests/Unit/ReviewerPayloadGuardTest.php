<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Interrogation\ReviewerPayloadGuard;
use InvalidArgumentException;
use Tests\TestCase;

class ReviewerPayloadGuardTest extends TestCase
{
    public function test_valid_pass_payload_passes(): void
    {
        $payload = [
            'verdict' => 'pass',
            'issues' => [],
            'required_changes' => [],
            'clarification_questions' => [],
            'confidence' => 0.85,
            'review_notes' => 'Looks good',
        ];

        $guard = new ReviewerPayloadGuard;
        $this->assertTrue($guard->validate($payload, 'summary'));
    }

    public function test_missing_verdict_fails(): void
    {
        $payload = ['issues' => [], 'confidence' => 0.5];
        $guard = new ReviewerPayloadGuard;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('verdict');
        $guard->validate($payload, 'summary');
    }

    public function test_invalid_verdict_enum_fails(): void
    {
        $payload = ['verdict' => 'approve', 'issues' => [], 'confidence' => 0.5];
        $guard = new ReviewerPayloadGuard;

        $this->expectException(InvalidArgumentException::class);
        $guard->validate($payload, 'summary');
    }

    public function test_invalid_severity_enum_fails(): void
    {
        $payload = [
            'verdict' => 'revise',
            'issues' => [['type' => 'contradiction', 'severity' => 'extreme', 'message' => 'test', 'evidence' => 'x']],
            'confidence' => 0.5,
        ];
        $guard = new ReviewerPayloadGuard;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('severity');
        $guard->validate($payload, 'summary');
    }

    public function test_clarification_questions_exceeds_max_fails(): void
    {
        $payload = [
            'verdict' => 'needs_clarification',
            'issues' => [],
            'clarification_questions' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'confidence' => 0.5,
        ];
        $guard = new ReviewerPayloadGuard;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('clarification_questions');
        $guard->validate($payload, 'summary');
    }

    public function test_clarification_questions_in_plan_context_fails(): void
    {
        $payload = [
            'verdict' => 'needs_clarification',
            'issues' => [],
            'clarification_questions' => ['Q1'],
            'confidence' => 0.5,
        ];
        $guard = new ReviewerPayloadGuard;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('plan');
        $guard->validate($payload, 'plan');
    }

    public function test_confidence_out_of_range_fails(): void
    {
        $payload = ['verdict' => 'pass', 'issues' => [], 'confidence' => 1.5];
        $guard = new ReviewerPayloadGuard;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('confidence');
        $guard->validate($payload, 'summary');
    }

    public function test_valid_revise_payload_with_issues_passes(): void
    {
        $payload = [
            'verdict' => 'revise',
            'issues' => [
                [
                    'type' => 'missing_requirement',
                    'severity' => 'high',
                    'message' => 'Missing auth flow',
                    'evidence' => 'Brief mentions auth but summary omits it',
                ],
            ],
            'required_changes' => ['Add authentication section'],
            'confidence' => 0.7,
        ];
        $guard = new ReviewerPayloadGuard;

        $this->assertTrue($guard->validate($payload, 'summary'));
    }
}
