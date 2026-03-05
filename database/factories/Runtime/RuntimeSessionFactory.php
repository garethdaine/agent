<?php

namespace Database\Factories\Runtime;

use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeSession>
 */
class RuntimeSessionFactory extends Factory
{
    protected $model = RuntimeSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => RuntimeSessionStatus::Active,
            'mode' => RuntimeMode::Safe,
            'title' => $this->faker->sentence(3),
            'workspace_root' => '/tmp/runtime-'.$this->faker->uuid(),
            'browser_persistence_mode' => BrowserPersistenceMode::Ephemeral,
            'started_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeSessionStatus::Pending,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeSessionStatus::Completed,
            'ended_at' => now(),
        ]);
    }

    public function standardMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => RuntimeMode::Standard,
        ]);
    }

    public function fullMode(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => RuntimeMode::Full,
        ]);
    }
}
