<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class AdversarialReviewerConfigTest extends TestCase
{
    public function test_adversarial_review_enabled_defaults_to_false(): void
    {
        $this->assertFalse(config('agent.interrogation.adversarial_review_enabled'));
    }

    public function test_summary_review_max_retries_defaults_to_3(): void
    {
        $this->assertSame(3, config('agent.interrogation.summary_review_max_retries'));
    }

    public function test_plan_review_max_retries_defaults_to_2(): void
    {
        $this->assertSame(2, config('agent.interrogation.plan_review_max_retries'));
    }

    public function test_review_warn_only_defaults_to_false(): void
    {
        $this->assertFalse(config('agent.interrogation.review_warn_only'));
    }

    public function test_review_severity_threshold_defaults_to_high(): void
    {
        $this->assertSame('high', config('agent.interrogation.review_severity_threshold'));
    }

    public function test_review_low_confidence_threshold_is_0_6(): void
    {
        $this->assertSame(0.6, config('agent.interrogation.review_low_confidence_threshold'));
    }

    public function test_review_max_clarification_questions_defaults_to_3(): void
    {
        $this->assertSame(3, config('agent.interrogation.review_max_clarification_questions'));
    }

    public function test_reviewer_model_override_defaults_to_null(): void
    {
        $this->assertNull(config('agent.interrogation.reviewer_model_override'));
    }
}
