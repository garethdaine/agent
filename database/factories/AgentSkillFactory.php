<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentSkill;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSkill>
 */
class AgentSkillFactory extends Factory
{
    protected $model = AgentSkill::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->slug(2),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(10),
            'version' => '1.0.0',
            'author' => fake()->name(),
            'risk_level' => AgentSkill::RISK_STANDARD,
            'status' => AgentSkill::STATUS_ACTIVE,
            'industries' => [],
            'memory_blocks' => [],
            'mcp_dependencies' => [],
            'tools_required' => [],
            'trigger_keywords' => [],
            'run_after' => [],
            'requires_approval' => false,
            'skill_path' => '/tmp/skills/'.fake()->slug(2),
            'checksum' => hash('sha256', fake()->uuid()),
            'file_hashes' => ['SKILL.md' => hash('sha256', 'test')],
            'validation_result' => ['overall_pass' => true, 'risk_score' => 0.1],
            'is_global' => false,
            'installed_by' => null,
            'installed_at' => now(),
            'updated_by' => null,
            'updated_at' => now(),
            'paused_by' => null,
            'paused_at' => null,
            'last_invoked_at' => null,
            'invocation_count' => 0,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => AgentSkill::STATUS_PAUSED,
            'paused_at' => now(),
        ]);
    }

    public function global(): static
    {
        return $this->state(fn () => [
            'is_global' => true,
        ]);
    }

    public function withRiskLevel(string $level): static
    {
        return $this->state(fn () => [
            'risk_level' => $level,
        ]);
    }
}
