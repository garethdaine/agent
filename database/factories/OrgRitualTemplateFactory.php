<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrgRitualTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgRitualTemplate>
 */
class OrgRitualTemplateFactory extends Factory
{
    protected $model = OrgRitualTemplate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'cron_expression' => '0 9 * * 1-5', // Weekdays at 9am
            'timezone' => 'UTC',
            'nl_source_metadata' => null,
            'phase_graph' => [
                ['id' => 'phase-1', 'name' => 'Initial Phase', 'order' => 1],
            ],
            'phase_role_mappings' => [
                'phase-1' => ['role' => 'analyst'],
            ],
            'context_inputs' => null,
            'verification_strategy' => null,
            'delivery_targets' => null,
            'escalation_timeout_seconds' => 3600,
            'notification_level' => OrgRitualTemplate::NOTIFICATION_LEVEL_ESCALATIONS_ONLY,
            'is_paused' => false,
            'archived_at' => null,
        ];
    }

    /**
     * Create a paused ritual template.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paused' => true,
        ]);
    }

    /**
     * Create an archived ritual template.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Set a custom phase graph.
     */
    public function withPhaseGraph(array $phaseGraph): static
    {
        return $this->state(fn (array $attributes) => [
            'phase_graph' => $phaseGraph,
        ]);
    }

    /**
     * Set custom phase role mappings.
     */
    public function withPhaseRoleMappings(array $mappings): static
    {
        return $this->state(fn (array $attributes) => [
            'phase_role_mappings' => $mappings,
        ]);
    }

    /**
     * Set context inputs for the ritual.
     */
    public function withContextInputs(array $inputs): static
    {
        return $this->state(fn (array $attributes) => [
            'context_inputs' => $inputs,
        ]);
    }

    /**
     * Set verification strategy for the ritual.
     */
    public function withVerificationStrategy(array $strategy): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_strategy' => $strategy,
        ]);
    }

    /**
     * Set delivery targets for the ritual.
     */
    public function withDeliveryTargets(array $targets): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_targets' => $targets,
        ]);
    }

    /**
     * Set a custom notification level.
     */
    public function withNotificationLevel(string $level): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_level' => $level,
        ]);
    }

    /**
     * Set a custom cron expression.
     */
    public function withCronExpression(string $expression): static
    {
        return $this->state(fn (array $attributes) => [
            'cron_expression' => $expression,
        ]);
    }
}
