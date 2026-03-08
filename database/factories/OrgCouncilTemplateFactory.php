<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrgCouncilTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgCouncilTemplate>
 */
class OrgCouncilTemplateFactory extends Factory
{
    protected $model = OrgCouncilTemplate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'member_list' => [],
            'evidence_payload_schema' => ['type' => 'object'],
            'member_response_schema' => ['type' => 'object'],
            'synthesis_mode' => fake()->randomElement(['majority', 'weighted', 'chair_decides']),
            'use_model_synthesis' => false,
            'report_sections' => ['agreements', 'conflicts', 'recommended_actions'],
            'archived_at' => null,
        ];
    }

    /**
     * Configure the model factory to create a template for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create an archived template.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Set the synthesis mode.
     */
    public function withSynthesisMode(string $mode): static
    {
        return $this->state(fn (array $attributes) => [
            'synthesis_mode' => $mode,
        ]);
    }

    /**
     * Enable model synthesis.
     */
    public function withModelSynthesis(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_model_synthesis' => true,
        ]);
    }

    /**
     * Set the member list.
     */
    public function withMembers(array $members): static
    {
        return $this->state(fn (array $attributes) => [
            'member_list' => $members,
        ]);
    }
}
