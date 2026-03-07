<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_skill_flag_constants_exist(): void
    {
        $this->assertSame('skills.enabled', FeatureFlagManager::SKILLS_ENABLED);
        $this->assertSame('skills.ui_enabled', FeatureFlagManager::SKILLS_UI_ENABLED);
        $this->assertSame('skills.library_enabled', FeatureFlagManager::SKILLS_LIBRARY_ENABLED);
        $this->assertSame('skills.auto_resolve', FeatureFlagManager::SKILLS_AUTO_RESOLVE);
        $this->assertSame('skills.validation.llm_review', FeatureFlagManager::SKILLS_VALIDATION_LLM_REVIEW);
        $this->assertSame('skills.validation.strict_mode', FeatureFlagManager::SKILLS_VALIDATION_STRICT_MODE);
    }

    public function test_all_skill_flags_are_in_definitions(): void
    {
        $managedKeys = FeatureFlagManager::managedKeys();

        $this->assertContains(FeatureFlagManager::SKILLS_ENABLED, $managedKeys);
        $this->assertContains(FeatureFlagManager::SKILLS_UI_ENABLED, $managedKeys);
        $this->assertContains(FeatureFlagManager::SKILLS_LIBRARY_ENABLED, $managedKeys);
        $this->assertContains(FeatureFlagManager::SKILLS_AUTO_RESOLVE, $managedKeys);
        $this->assertContains(FeatureFlagManager::SKILLS_VALIDATION_LLM_REVIEW, $managedKeys);
        $this->assertContains(FeatureFlagManager::SKILLS_VALIDATION_STRICT_MODE, $managedKeys);
    }

    public function test_all_skill_flags_default_to_false(): void
    {
        $manager = app(FeatureFlagManager::class);

        $this->assertFalse($manager->enabled(FeatureFlagManager::SKILLS_ENABLED));
        $this->assertFalse($manager->enabled(FeatureFlagManager::SKILLS_UI_ENABLED));
        $this->assertFalse($manager->enabled(FeatureFlagManager::SKILLS_LIBRARY_ENABLED));
        $this->assertFalse($manager->enabled(FeatureFlagManager::SKILLS_AUTO_RESOLVE));
        $this->assertFalse($manager->enabled(FeatureFlagManager::SKILLS_VALIDATION_LLM_REVIEW));
        $this->assertFalse($manager->enabled(FeatureFlagManager::SKILLS_VALIDATION_STRICT_MODE));
    }

    public function test_get_skills_flags_returns_all_six_keys(): void
    {
        $flags = FeatureFlagManager::getSkillsFlags();

        $this->assertCount(6, $flags);
        $this->assertContains(FeatureFlagManager::SKILLS_ENABLED, $flags);
        $this->assertContains(FeatureFlagManager::SKILLS_UI_ENABLED, $flags);
        $this->assertContains(FeatureFlagManager::SKILLS_LIBRARY_ENABLED, $flags);
        $this->assertContains(FeatureFlagManager::SKILLS_AUTO_RESOLVE, $flags);
        $this->assertContains(FeatureFlagManager::SKILLS_VALIDATION_LLM_REVIEW, $flags);
        $this->assertContains(FeatureFlagManager::SKILLS_VALIDATION_STRICT_MODE, $flags);
    }
}
