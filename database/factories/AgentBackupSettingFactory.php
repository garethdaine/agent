<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentBackupSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentBackupSetting>
 */
class AgentBackupSettingFactory extends Factory
{
    protected $model = AgentBackupSetting::class;

    public function definition(): array
    {
        return [
            'is_enabled' => true,
            'timezone' => 'UTC',
            'run_hour' => $this->faker->numberBetween(0, 23),
            'run_minute' => $this->faker->numberBetween(0, 59),
            'retention_days' => 30,
            'last_run_at' => null,
            'last_status' => null,
            'last_error' => null,
            'updated_by_user_id' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
