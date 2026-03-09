<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemorySetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemorySetting>
 */
class MemorySettingFactory extends Factory
{
    protected $model = MemorySetting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => $this->faker->unique()->slug(2),
            'value' => $this->faker->word(),
        ];
    }
}
