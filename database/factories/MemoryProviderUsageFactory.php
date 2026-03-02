<?php

namespace Database\Factories;

use App\Models\MemoryProviderUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryProviderUsage>
 */
class MemoryProviderUsageFactory extends Factory
{
    protected $model = MemoryProviderUsage::class;

    public function definition(): array
    {
        $provider = $this->faker->randomElement([
            MemoryProviderUsage::PROVIDER_OPENAI,
            MemoryProviderUsage::PROVIDER_ANTHROPIC,
        ]);

        $models = [
            MemoryProviderUsage::PROVIDER_OPENAI => ['gpt-4o-mini', 'gpt-4.1-nano', 'text-embedding-3-small'],
            MemoryProviderUsage::PROVIDER_ANTHROPIC => ['claude-3-haiku-20240307'],
        ];

        return [
            'user_id' => User::factory(),
            'run_id' => null,
            'provider' => $provider,
            'model' => $this->faker->randomElement($models[$provider]),
            'operation' => $this->faker->randomElement([
                MemoryProviderUsage::OPERATION_EXTRACTION,
                MemoryProviderUsage::OPERATION_SUMMARIZATION,
                MemoryProviderUsage::OPERATION_EMBEDDING,
            ]),
            'input_tokens' => $this->faker->numberBetween(100, 5000),
            'output_tokens' => $this->faker->optional(0.7)->numberBetween(50, 1000),
            'cost_estimate_usd' => $this->faker->randomFloat(6, 0.0001, 0.05),
            'created_at' => now(),
        ];
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => MemoryProviderUsage::PROVIDER_OPENAI,
            'model' => 'gpt-4o-mini',
        ]);
    }

    public function anthropic(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => MemoryProviderUsage::PROVIDER_ANTHROPIC,
            'model' => 'claude-3-haiku-20240307',
        ]);
    }

    public function embedding(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation' => MemoryProviderUsage::OPERATION_EMBEDDING,
            'output_tokens' => null,
        ]);
    }
}
