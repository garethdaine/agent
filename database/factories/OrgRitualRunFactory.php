<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationGraph;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrgRitualRun>
 */
class OrgRitualRunFactory extends Factory
{
    protected $model = OrgRitualRun::class;

    public function definition(): array
    {
        return [
            'ritual_template_id' => OrgRitualTemplate::factory(),
            'user_id' => User::factory(),
            'state' => OrgRitualRun::STATE_DRAFT,
            'delegation_graph_id' => null,
            'phase_outputs' => null,
            'started_at' => null,
            'completed_at' => null,
            'correlation_id' => Str::uuid()->toString(),
        ];
    }

    /**
     * Configure the run for a specific template.
     * Ensures the run belongs to the same user as the template.
     */
    public function forTemplate(OrgRitualTemplate $template): static
    {
        return $this->state(fn (array $attributes) => [
            'ritual_template_id' => $template->id,
            'user_id' => $template->user_id,
        ]);
    }

    /**
     * Set the run to a specific state.
     */
    public function withState(string $state): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => $state,
        ]);
    }

    /**
     * Create a queued run.
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgRitualRun::STATE_QUEUED,
        ]);
    }

    /**
     * Create a running run.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgRitualRun::STATE_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Create a succeeded run.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgRitualRun::STATE_SUCCEEDED,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);
    }

    /**
     * Create a failed run.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgRitualRun::STATE_FAILED,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);
    }

    /**
     * Create a partial run.
     */
    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => OrgRitualRun::STATE_PARTIAL,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);
    }

    /**
     * Associate with a delegation graph.
     */
    public function withDelegationGraph(DelegationGraph $graph): static
    {
        return $this->state(fn (array $attributes) => [
            'delegation_graph_id' => $graph->id,
        ]);
    }

    /**
     * Set phase outputs.
     */
    public function withPhaseOutputs(array $outputs): static
    {
        return $this->state(fn (array $attributes) => [
            'phase_outputs' => $outputs,
        ]);
    }

    /**
     * Set a specific correlation ID.
     */
    public function withCorrelationId(string $correlationId): static
    {
        return $this->state(fn (array $attributes) => [
            'correlation_id' => $correlationId,
        ]);
    }
}
