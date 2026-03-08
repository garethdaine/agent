<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrgAgentProfile;
use App\Models\OrgEscalation;
use App\Models\OrgRitualRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgEscalation>
 */
class OrgEscalationFactory extends Factory
{
    protected $model = OrgEscalation::class;

    public function definition(): array
    {
        return [
            'ritual_run_id' => OrgRitualRun::factory(),
            'escalation_type' => fake()->randomElement(OrgEscalation::TYPES),
            'escalated_to_agent_id' => OrgAgentProfile::factory(),
            'state' => OrgEscalation::STATE_PENDING,
            'timeout_at' => now()->addHour(),
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }

    /**
     * Associate with a specific ritual run.
     */
    public function forRun(OrgRitualRun $run): static
    {
        return $this->state(fn (array $attributes) => [
            'ritual_run_id' => $run->id,
        ]);
    }

    /**
     * Set the escalation target agent.
     */
    public function escalatedTo(OrgAgentProfile $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'escalated_to_agent_id' => $agent->id,
        ]);
    }

    /**
     * Create a budget escalation.
     */
    public function budget(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => OrgEscalation::TYPE_BUDGET,
        ]);
    }

    /**
     * Create a verification escalation.
     */
    public function verification(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => OrgEscalation::TYPE_VERIFICATION,
        ]);
    }

    /**
     * Create an approval escalation.
     */
    public function approval(): static
    {
        return $this->state(fn (array $attributes) => [
            'escalation_type' => OrgEscalation::TYPE_APPROVAL,
        ]);
    }

    /**
     * Create an approved escalation.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgEscalation::STATE_APPROVED,
            'resolved_by' => 'admin-user',
            'resolved_at' => now(),
            'resolution_notes' => 'Approved by admin',
        ]);
    }

    /**
     * Create a rejected escalation.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgEscalation::STATE_REJECTED,
            'resolved_by' => 'admin-user',
            'resolved_at' => now(),
            'resolution_notes' => 'Rejected by admin',
        ]);
    }

    /**
     * Create a timed out escalation.
     */
    public function timedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgEscalation::STATE_TIMED_OUT,
            'timeout_at' => now()->subHour(),
        ]);
    }

    /**
     * Set the timeout timestamp.
     */
    public function withTimeout($timeout): static
    {
        return $this->state(fn (array $attributes) => [
            'timeout_at' => $timeout,
        ]);
    }

    /**
     * Create an expired (but still pending) escalation.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgEscalation::STATE_PENDING,
            'timeout_at' => now()->subHour(),
        ]);
    }
}
