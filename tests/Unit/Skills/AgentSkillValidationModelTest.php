<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Models\AgentSkillValidation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSkillValidationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_return_correct_types(): void
    {
        $casts = (new AgentSkillValidation)->getCasts();

        $this->assertSame('array', $casts['validation_result']);
        $this->assertStringContainsString('decimal', $casts['risk_score']);
        $this->assertSame('boolean', $casts['overall_pass']);
    }

    public function test_belongs_to_team_relationship(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $validation = AgentSkillValidation::factory()->for($team)->create();

        $this->assertTrue($validation->team->is($team));
    }

    public function test_factory_creates_valid_model(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $validation = AgentSkillValidation::factory()->for($user->currentTeam)->create();

        $this->assertNotNull($validation->id);
        $this->assertNotNull($validation->skill_name);
        $this->assertNotNull($validation->team_id);
    }
}
