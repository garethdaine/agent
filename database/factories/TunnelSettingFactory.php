<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TunnelSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TunnelSetting>
 */
class TunnelSettingFactory extends Factory
{
    protected $model = TunnelSetting::class;

    public function definition(): array
    {
        return [
            'settings' => [
                'provider' => 'cloudflare',
                'subdomain' => $this->faker->slug(1),
            ],
            'status' => $this->faker->randomElement(['active', 'inactive', 'error']),
        ];
    }
}
