<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRunEvent>
 */
class AgentRunEventFactory extends Factory
{
    protected $model = AgentRunEvent::class;

    public function definition(): array
    {
        return [
            'agent_job_run_id' => AgentJobRun::factory(),
            'event_type' => $this->faker->randomElement(['output', 'error', 'status', 'tool_use', 'approval_request']),
            'sequence' => $this->faker->numberBetween(1, 100),
            'payload' => $this->faker->sentence(),
            'reasoning_step' => null,
            'event_ts' => now(),
        ];
    }
}
