<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InterrogationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterrogationSetting>
 */
class InterrogationSettingFactory extends Factory
{
    protected $model = InterrogationSetting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => $this->faker->unique()->slug(2),
            'value' => [$this->faker->word() => $this->faker->boolean()],
        ];
    }
}
