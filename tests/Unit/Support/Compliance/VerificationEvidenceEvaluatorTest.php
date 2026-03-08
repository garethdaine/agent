<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compliance;

use App\Enums\TaskCategory;
use App\Support\Compliance\DTOs\VerificationResult;
use App\Support\Compliance\VerificationEvidenceEvaluator;
use PHPUnit\Framework\TestCase;

class VerificationEvidenceEvaluatorTest extends TestCase
{
    private VerificationEvidenceEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new VerificationEvidenceEvaluator;
    }

    // ========================================================================
    // Feature Category Tests
    // ========================================================================

    public function test_feature_category_requires_automated_check_and_ai_critic(): void
    {
        $evidence = [
            'automated_check' => true,
            'ai_critic' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, $evidence);

        $this->assertInstanceOf(VerificationResult::class, $result);
        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEqualsCanonicalizing(['automated_check', 'ai_critic'], $result->present);
    }

    public function test_feature_category_fails_when_missing_automated_check(): void
    {
        $evidence = [
            'ai_critic' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertContains('automated_check', $result->missing);
        $this->assertNotContains('ai_critic', $result->missing);
        $this->assertContains('ai_critic', $result->present);
    }

    public function test_feature_category_fails_when_missing_ai_critic(): void
    {
        $evidence = [
            'automated_check' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertContains('ai_critic', $result->missing);
        $this->assertNotContains('automated_check', $result->missing);
        $this->assertContains('automated_check', $result->present);
    }

    public function test_feature_category_fails_when_missing_all_evidence(): void
    {
        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, []);

        $this->assertFalse($result->satisfied);
        $this->assertEqualsCanonicalizing(['automated_check', 'ai_critic'], $result->missing);
        $this->assertEmpty($result->present);
    }

    // ========================================================================
    // Bugfix Category Tests
    // ========================================================================

    public function test_bugfix_category_requires_all_three_evidence_types(): void
    {
        $evidence = [
            'automated_check' => true,
            'ai_critic' => true,
            'human_approval' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::BUGFIX, $evidence);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEqualsCanonicalizing(
            ['automated_check', 'ai_critic', 'human_approval'],
            $result->present
        );
    }

    public function test_bugfix_category_fails_when_missing_human_approval(): void
    {
        $evidence = [
            'automated_check' => true,
            'ai_critic' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::BUGFIX, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertContains('human_approval', $result->missing);
        $this->assertCount(1, $result->missing);
    }

    public function test_bugfix_category_fails_when_missing_multiple_evidence(): void
    {
        $evidence = [
            'automated_check' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::BUGFIX, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertCount(2, $result->missing);
        $this->assertContains('ai_critic', $result->missing);
        $this->assertContains('human_approval', $result->missing);
    }

    // ========================================================================
    // Refactor Category Tests
    // ========================================================================

    public function test_refactor_category_requires_automated_check_only(): void
    {
        $evidence = [
            'automated_check' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::REFACTOR, $evidence);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEquals(['automated_check'], $result->present);
    }

    public function test_refactor_category_fails_when_missing_automated_check(): void
    {
        $result = $this->evaluator->evaluate(TaskCategory::REFACTOR, []);

        $this->assertFalse($result->satisfied);
        $this->assertEquals(['automated_check'], $result->missing);
    }

    public function test_refactor_category_passes_even_with_extra_evidence(): void
    {
        $evidence = [
            'automated_check' => true,
            'ai_critic' => true,
            'human_approval' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::REFACTOR, $evidence);

        $this->assertTrue($result->satisfied);
        // Only automated_check should be tracked in present since that's the only requirement
        $this->assertEquals(['automated_check'], $result->present);
    }

    // ========================================================================
    // Docs Category Tests
    // ========================================================================

    public function test_docs_category_requires_no_evidence(): void
    {
        $result = $this->evaluator->evaluate(TaskCategory::DOCS, []);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEmpty($result->present);
    }

    public function test_docs_category_always_passes_regardless_of_evidence(): void
    {
        $evidence = [
            'automated_check' => true,
            'ai_critic' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::DOCS, $evidence);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        // No requirements means nothing tracked in present
        $this->assertEmpty($result->present);
    }

    // ========================================================================
    // Test Category Tests
    // ========================================================================

    public function test_test_category_requires_no_evidence(): void
    {
        $result = $this->evaluator->evaluate(TaskCategory::TEST, []);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEmpty($result->present);
    }

    public function test_test_category_always_passes_regardless_of_evidence(): void
    {
        $evidence = [
            'automated_check' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::TEST, $evidence);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEmpty($result->present);
    }

    // ========================================================================
    // Custom Category Tests
    // ========================================================================

    public function test_custom_category_inherits_feature_requirements(): void
    {
        $evidence = [
            'automated_check' => true,
            'ai_critic' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::CUSTOM, $evidence);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
        $this->assertEqualsCanonicalizing(['automated_check', 'ai_critic'], $result->present);
    }

    public function test_custom_category_fails_like_feature_when_missing_evidence(): void
    {
        $evidence = [
            'automated_check' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::CUSTOM, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertContains('ai_critic', $result->missing);
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    public function test_falsy_evidence_values_are_treated_as_missing(): void
    {
        $evidence = [
            'automated_check' => false,
            'ai_critic' => null,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertEqualsCanonicalizing(['automated_check', 'ai_critic'], $result->missing);
        $this->assertEmpty($result->present);
    }

    public function test_empty_string_evidence_is_treated_as_missing(): void
    {
        $evidence = [
            'automated_check' => '',
            'ai_critic' => true,
        ];

        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, $evidence);

        $this->assertFalse($result->satisfied);
        $this->assertContains('automated_check', $result->missing);
        $this->assertContains('ai_critic', $result->present);
    }

    public function test_truthy_string_evidence_is_accepted(): void
    {
        $evidence = [
            'automated_check' => 'passed',
            'ai_critic' => ['score' => 85],
        ];

        $result = $this->evaluator->evaluate(TaskCategory::FEATURE, $evidence);

        $this->assertTrue($result->satisfied);
        $this->assertEmpty($result->missing);
    }

    // ========================================================================
    // getRequirements Tests
    // ========================================================================

    public function test_get_requirements_returns_correct_list_for_each_category(): void
    {
        $this->assertEqualsCanonicalizing(
            ['automated_check', 'ai_critic'],
            $this->evaluator->getRequirements(TaskCategory::FEATURE)
        );

        $this->assertEqualsCanonicalizing(
            ['automated_check', 'ai_critic', 'human_approval'],
            $this->evaluator->getRequirements(TaskCategory::BUGFIX)
        );

        $this->assertEquals(
            ['automated_check'],
            $this->evaluator->getRequirements(TaskCategory::REFACTOR)
        );

        $this->assertEmpty($this->evaluator->getRequirements(TaskCategory::DOCS));

        $this->assertEmpty($this->evaluator->getRequirements(TaskCategory::TEST));

        $this->assertEqualsCanonicalizing(
            ['automated_check', 'ai_critic'],
            $this->evaluator->getRequirements(TaskCategory::CUSTOM)
        );
    }
}
