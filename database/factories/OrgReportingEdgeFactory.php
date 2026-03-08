<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrgAgentProfile;
use App\Models\OrgReportingEdge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgReportingEdge>
 */
class OrgReportingEdgeFactory extends Factory
{
    protected $model = OrgReportingEdge::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'subordinate_agent_id' => OrgAgentProfile::factory()->for($user),
            'manager_agent_id' => OrgAgentProfile::factory()->for($user),
            'user_id' => $user->id,
        ];
    }

    /**
     * Create an edge for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'subordinate_agent_id' => OrgAgentProfile::factory()->for($user),
            'manager_agent_id' => OrgAgentProfile::factory()->for($user),
        ]);
    }

    /**
     * Set the subordinate agent.
     */
    public function withSubordinate(OrgAgentProfile $subordinate): static
    {
        return $this->state(fn (array $attributes) => [
            'subordinate_agent_id' => $subordinate->id,
            'user_id' => $subordinate->user_id,
        ]);
    }

    /**
     * Set the manager agent.
     */
    public function withManager(OrgAgentProfile $manager): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_agent_id' => $manager->id,
        ]);
    }
}
