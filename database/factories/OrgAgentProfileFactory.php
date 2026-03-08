<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegateeProfile;
use App\Models\OrgAgentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgAgentProfile>
 */
class OrgAgentProfileFactory extends Factory
{
    protected $model = OrgAgentProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->slug(2),
            'role_slug' => fake()->randomElement(['developer', 'analyst', 'reviewer', 'coordinator']),
            'role_description' => fake()->sentence(),
            'delegatee_profile_id' => DelegateeProfile::factory(),
            'capability_bindings' => [],
            'authority_overrides' => [],
            'default_output_schema' => null,
            'parent_agent_id' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Configure the model factory to create a profile for a specific user.
     * This ensures the delegatee profile also belongs to the same user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'delegatee_profile_id' => DelegateeProfile::factory()->for($user),
        ]);
    }

    /**
     * Create an archived profile.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Set capability bindings for the profile.
     */
    public function withCapabilityBindings(array $bindings): static
    {
        return $this->state(fn (array $attributes) => [
            'capability_bindings' => $bindings,
        ]);
    }

    /**
     * Set authority overrides for the profile.
     */
    public function withAuthorityOverrides(array $overrides): static
    {
        return $this->state(fn (array $attributes) => [
            'authority_overrides' => $overrides,
        ]);
    }

    /**
     * Set a default output schema for the profile.
     */
    public function withOutputSchema(array $schema): static
    {
        return $this->state(fn (array $attributes) => [
            'default_output_schema' => $schema,
        ]);
    }

    /**
     * Set a parent agent for this profile.
     */
    public function withParent(OrgAgentProfile $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_agent_id' => $parent->id,
        ]);
    }
}
