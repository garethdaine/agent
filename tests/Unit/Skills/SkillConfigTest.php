<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use Tests\TestCase;

class SkillConfigTest extends TestCase
{
    public function test_skills_config_returns_array_with_expected_keys(): void
    {
        $skills = config('agent.skills');

        $this->assertIsArray($skills);
        $this->assertArrayHasKey('storage_path', $skills);
        $this->assertArrayHasKey('global_storage_path', $skills);
        $this->assertArrayHasKey('library_manifest_path', $skills);
        $this->assertArrayHasKey('max_skills_per_invocation', $skills);
        $this->assertArrayHasKey('validation', $skills);
        $this->assertArrayHasKey('risk_level_trust_thresholds', $skills);
    }

    public function test_validation_config_has_expected_keys(): void
    {
        $validation = config('agent.skills.validation');

        $this->assertArrayHasKey('weights_without_llm', $validation);
        $this->assertArrayHasKey('weights_with_llm', $validation);
        $this->assertArrayHasKey('thresholds', $validation);
    }

    public function test_validation_weights_without_llm_sum_to_one(): void
    {
        $weights = config('agent.skills.validation.weights_without_llm');

        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.001);
    }

    public function test_validation_weights_with_llm_sum_to_one(): void
    {
        $weights = config('agent.skills.validation.weights_with_llm');

        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.001);
    }

    public function test_thresholds_are_ordered_correctly(): void
    {
        $thresholds = config('agent.skills.validation.thresholds');

        $this->assertLessThan($thresholds['warn'], $thresholds['clean']);
        $this->assertLessThan($thresholds['admin_confirm'], $thresholds['warn']);
    }
}
