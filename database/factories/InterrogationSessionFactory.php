<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InterrogationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterrogationSession>
 */
class InterrogationSessionFactory extends Factory
{
    protected $model = InterrogationSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'runner_type' => 'claude',
            'project_directory' => '/tmp/test-project',
            'interrogation_type' => InterrogationSession::TYPE_FEATURE,
            'status' => InterrogationSession::STATUS_INTERROGATING,
            'phase' => InterrogationSession::PHASE_INTERROGATION,
            'metadata_json' => [],
        ];
    }

    public function withBrief(string $brief): static
    {
        return $this->state(fn (array $attributes) => [
            'feature_brief' => $brief,
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata_json' => array_merge($attributes['metadata_json'] ?? [], $metadata),
        ]);
    }

    public function summarizing(): static
    {
        return $this->state(fn () => [
            'status' => InterrogationSession::STATUS_SUMMARIZING,
            'phase' => InterrogationSession::PHASE_SUMMARY,
        ]);
    }

    public function planning(): static
    {
        return $this->state(fn () => [
            'status' => InterrogationSession::STATUS_PLANNING,
            'phase' => InterrogationSession::PHASE_PLANNING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withGitSettings(array $overrides = []): static
    {
        $defaults = [
            'commit_enabled' => false,
            'conventional_commits' => false,
            'worktree_enabled' => false,
            'branching_enabled' => false,
            'branch_prefix' => null,
            'target_branch' => null,
        ];

        return $this->withMetadata(['git' => array_merge($defaults, $overrides)]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withBuildSettings(array $overrides = []): static
    {
        $defaults = [
            'auto_advance_tasks' => true,
        ];

        return $this->withMetadata(['build_settings' => array_merge($defaults, $overrides)]);
    }
}
