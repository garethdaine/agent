<?php

declare(strict_types=1);

namespace Tests\Feature\Skills;

use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Mockery;
use Tests\TestCase;

class SkillUiRoutesTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['onboarding_completed_at' => now()]);
    }

    public function test_skills_index_accessible_when_flags_enabled(): void
    {
        $this->enableSkillsUiFlags();

        $response = $this->actingAs($this->user)->get('/tools/skills');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Tools/Skills/Index'));
    }

    public function test_skills_index_redirects_when_flag_disabled(): void
    {
        $this->disableSkillsUiFlags();

        $response = $this->actingAs($this->user)->get('/tools/skills');

        $response->assertRedirect('/tools');
    }

    public function test_skills_install_page_accessible(): void
    {
        $this->enableSkillsUiFlags();

        $response = $this->actingAs($this->user)->get('/tools/skills/install');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Tools/Skills/Install'));
    }

    public function test_skills_library_page_accessible(): void
    {
        $this->enableSkillsUiFlags();

        $response = $this->actingAs($this->user)->get('/tools/skills/library');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Tools/Skills/Library'));
    }

    public function test_skills_show_page_accessible(): void
    {
        $this->enableSkillsUiFlags();

        $response = $this->actingAs($this->user)->get('/tools/skills/test-skill-id');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tools/Skills/Show')
            ->where('skillId', 'test-skill-id')
        );
    }

    public function test_tools_index_includes_skills_prop(): void
    {
        $this->enableSkillsUiFlags();

        $response = $this->actingAs($this->user)->get('/tools');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tools/Index')
            ->has('skills')
            ->where('skills.available', true)
        );
    }

    public function test_requires_authentication(): void
    {
        $response = $this->get('/tools/skills');

        $response->assertRedirect('/login');
    }

    private function enableSkillsUiFlags(): void
    {
        $flagManager = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_UI_ENABLED)
            ->andReturn(true);
        $flagManager->shouldReceive('enabled')
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);
    }

    private function disableSkillsUiFlags(): void
    {
        $flagManager = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flagManager->shouldReceive('enabled')
            ->with(FeatureFlagManager::SKILLS_UI_ENABLED)
            ->andReturn(false);
        $flagManager->shouldReceive('enabled')
            ->andReturn(false);
        $this->app->instance(FeatureFlagManager::class, $flagManager);
    }
}
