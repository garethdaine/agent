<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Security\InjectionDetectionResult;
use App\Enums\Security\InjectionAction;
use Tests\TestCase;

class InjectionDetectionResultTest extends TestCase
{
    public function test_dto_is_readonly_with_all_fields_accessible(): void
    {
        $result = new InjectionDetectionResult(
            score: 0.75,
            matchedPatterns: [['rule_id' => 'abc', 'pattern' => 'test']],
            recommendedAction: InjectionAction::Warn,
            scanDurationMs: 12,
        );

        $this->assertSame(0.75, $result->score);
        $this->assertCount(1, $result->matchedPatterns);
        $this->assertSame(InjectionAction::Warn, $result->recommendedAction);
        $this->assertSame(12, $result->scanDurationMs);
    }

    public function test_empty_matched_patterns_with_zero_score_is_valid(): void
    {
        $result = new InjectionDetectionResult(
            score: 0.0,
            matchedPatterns: [],
            recommendedAction: InjectionAction::Log,
            scanDurationMs: 1,
        );

        $this->assertSame(0.0, $result->score);
        $this->assertEmpty($result->matchedPatterns);
        $this->assertSame(InjectionAction::Log, $result->recommendedAction);
    }
}
