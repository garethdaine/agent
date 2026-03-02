<?php

namespace Database\Factories;

use App\Models\OrgAgentProfile;
use App\Models\OrgCostLedger;
use App\Models\OrgRitualRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgCostLedger>
 */
class OrgCostLedgerFactory extends Factory
{
    protected $model = OrgCostLedger::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'org_agent_id' => null,
            'ritual_run_id' => null,
            'token_count' => fake()->numberBetween(100, 10000),
            'runtime_ms' => fake()->numberBetween(500, 60000),
            'estimated_cost_usd' => fake()->randomFloat(6, 0.0001, 0.1),
            'recorded_at' => now(),
        ];
    }

    /**
     * Associate with a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Associate with a specific agent.
     */
    public function forAgent(OrgAgentProfile $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'org_agent_id' => $agent->id,
            'user_id' => $agent->user_id,
        ]);
    }

    /**
     * Associate with a specific ritual run.
     */
    public function forRitualRun(OrgRitualRun $run): static
    {
        return $this->state(fn (array $attributes) => [
            'ritual_run_id' => $run->id,
            'user_id' => $run->user_id,
        ]);
    }

    /**
     * Set specific cost values.
     */
    public function withCost(int $tokenCount, int $runtimeMs, float $costUsd): static
    {
        return $this->state(fn (array $attributes) => [
            'token_count' => $tokenCount,
            'runtime_ms' => $runtimeMs,
            'estimated_cost_usd' => $costUsd,
        ]);
    }

    /**
     * Set the recorded timestamp.
     */
    public function recordedAt($timestamp): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $timestamp,
        ]);
    }
}
