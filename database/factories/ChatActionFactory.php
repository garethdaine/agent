<?php

namespace Database\Factories;

use App\Models\ChatAction;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatAction>
 */
class ChatActionFactory extends Factory
{
    protected $model = ChatAction::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'chat_message_id' => ChatMessage::factory(),
            'action_type' => $this->faker->randomElement([
                ChatAction::ACTION_JOBS_LIST,
                ChatAction::ACTION_RUNS_LIST_ACTIVE,
            ]),
            'parameters' => [],
            'status' => ChatAction::STATUS_PENDING,
            'result' => null,
            'error' => null,
            'requires_confirmation' => false,
            'confirmed_at' => null,
            'created_at' => now(),
            'executed_at' => null,
        ];
    }

    public function forMessage(ChatMessage $message): static
    {
        return $this->state(fn (array $attributes) => [
            'chat_message_id' => $message->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatAction::STATUS_PENDING,
            'executed_at' => null,
        ]);
    }

    public function executing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatAction::STATUS_EXECUTING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatAction::STATUS_COMPLETED,
            'executed_at' => now(),
            'result' => ['success' => true],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatAction::STATUS_FAILED,
            'executed_at' => now(),
            'error' => 'Action execution failed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatAction::STATUS_CANCELLED,
        ]);
    }

    public function timeout(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatAction::STATUS_TIMEOUT,
            'error' => 'Action timed out',
        ]);
    }

    public function requiresConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_confirmation' => true,
            'confirmed_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_confirmation' => true,
            'confirmed_at' => now(),
        ]);
    }

    public function destructive(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => $this->faker->randomElement(ChatAction::DESTRUCTIVE_ACTIONS),
            'requires_confirmation' => true,
        ]);
    }

    public function jobsCreate(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => ChatAction::ACTION_JOBS_CREATE,
            'parameters' => [
                'name' => $this->faker->sentence(3),
                'cron_expression' => '0 * * * *',
            ],
        ]);
    }

    public function jobsDelete(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => ChatAction::ACTION_JOBS_DELETE,
            'requires_confirmation' => true,
            'parameters' => [
                'job_id' => $this->faker->numberBetween(1, 1000),
            ],
        ]);
    }

    public function runsStop(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => ChatAction::ACTION_RUNS_STOP,
            'requires_confirmation' => true,
            'parameters' => [
                'run_id' => $this->faker->numberBetween(1, 1000),
            ],
        ]);
    }
}
