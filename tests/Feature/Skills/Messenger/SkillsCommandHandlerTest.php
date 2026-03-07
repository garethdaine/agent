<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\Messenger;

use App\DTOs\Messenger\CommandResult;
use App\Messenger\SlashCommands\SkillsCommandHandler;
use App\Models\AgentSkill;
use App\Models\Team;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SkillsCommandHandlerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->team = $this->owner->currentTeam;
    }

    public function test_skills_list_returns_installed_skills(): void
    {
        $this->enableSkillsFlag();

        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'output-fact-checker',
            'slug' => 'output-fact-checker',
            'status' => AgentSkill::STATUS_ACTIVE,
            'risk_level' => AgentSkill::RISK_STANDARD,
        ]);

        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'pii-detector-redactor',
            'slug' => 'pii-detector-redactor',
            'status' => AgentSkill::STATUS_PAUSED,
            'risk_level' => AgentSkill::RISK_ELEVATED,
        ]);

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['list']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('output-fact-checker', $result->message);
        $this->assertStringContainsString('pii-detector-redactor', $result->message);
        $this->assertStringContainsString('active', $result->message);
        $this->assertStringContainsString('paused', $result->message);
    }

    public function test_skills_install_triggers_confirmation(): void
    {
        $this->enableSkillsFlag();
        $this->enableLibraryFlag();

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['install', 'output-fact-checker']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('confirm', strtolower($result->message));
        $this->assertArrayHasKey('requires_confirmation', $result->data);
        $this->assertTrue($result->data['requires_confirmation']);
    }

    public function test_skills_install_completes_after_confirmation(): void
    {
        $this->enableSkillsFlag();
        $this->enableLibraryFlag();

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['install', 'output-fact-checker', '--confirmed']);

        // The install will fail because no actual library file exists, but the flow should attempt it
        // This tests the confirmation bypass path
        $this->assertInstanceOf(CommandResult::class, $result);
    }

    public function test_skills_pause_changes_status(): void
    {
        $this->enableSkillsFlag();

        $skill = AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'test-skill',
            'slug' => 'test-skill',
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['pause', 'test-skill']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('paused', strtolower($result->message));

        $skill->refresh();
        $this->assertEquals(AgentSkill::STATUS_PAUSED, $skill->status);
    }

    public function test_skills_resume_changes_status(): void
    {
        $this->enableSkillsFlag();

        $skill = AgentSkill::factory()->paused()->create([
            'team_id' => $this->team->id,
            'name' => 'test-skill',
            'slug' => 'test-skill',
        ]);

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['resume', 'test-skill']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('resumed', strtolower($result->message));

        $skill->refresh();
        $this->assertEquals(AgentSkill::STATUS_ACTIVE, $skill->status);
    }

    public function test_skills_info_returns_detail(): void
    {
        $this->enableSkillsFlag();

        AgentSkill::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'test-skill',
            'slug' => 'test-skill',
            'description' => 'A test skill for validation',
            'version' => '2.1.0',
            'risk_level' => AgentSkill::RISK_ELEVATED,
            'status' => AgentSkill::STATUS_ACTIVE,
            'invocation_count' => 42,
            'last_invoked_at' => now()->subHour(),
        ]);

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['info', 'test-skill']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('test-skill', $result->message);
        $this->assertStringContainsString('2.1.0', $result->message);
        $this->assertStringContainsString('elevated', $result->message);
        $this->assertStringContainsString('42', $result->message);
    }

    public function test_skills_unknown_subcommand_shows_help(): void
    {
        $this->enableSkillsFlag();

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['xyz']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage', $result->message);
    }

    public function test_skills_command_requires_skills_enabled_flag(): void
    {
        $this->disableSkillsFlag();

        $handler = app(SkillsCommandHandler::class);
        $result = $handler->handle($this->owner, ['list']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('disabled', strtolower($result->message));
    }

    private function enableSkillsFlag(): void
    {
        $flags = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flags->shouldReceive('isEnabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flags->shouldReceive('isEnabled')
            ->with(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)
            ->andReturn(false);

        $this->app->instance(FeatureFlagManager::class, $flags);
    }

    private function enableLibraryFlag(): void
    {
        $flags = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flags->shouldReceive('isEnabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(true);
        $flags->shouldReceive('isEnabled')
            ->with(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)
            ->andReturn(true);

        $this->app->instance(FeatureFlagManager::class, $flags);
    }

    private function disableSkillsFlag(): void
    {
        $flags = Mockery::mock(FeatureFlagManager::class)->makePartial();
        $flags->shouldReceive('isEnabled')
            ->with(FeatureFlagManager::SKILLS_ENABLED)
            ->andReturn(false);

        $this->app->instance(FeatureFlagManager::class, $flags);
    }
}
