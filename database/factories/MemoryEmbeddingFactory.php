<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MemoryEmbedding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryEmbedding>
 */
class MemoryEmbeddingFactory extends Factory
{
    protected $model = MemoryEmbedding::class;

    public function definition(): array
    {
        $content = $this->faker->paragraph();

        return [
            'user_id' => User::factory(),
            'source_type' => MemoryEmbedding::SOURCE_CONVERSATION,
            'source_id' => (string) $this->faker->uuid(),
            'content' => $content,
            'content_hash' => MemoryEmbedding::generateContentHash($content),
            'metadata_json' => null,
            'classification' => 'internal',
            'importance_score' => $this->faker->randomFloat(2, 0.3, 0.9),
            'access_count' => $this->faker->numberBetween(0, 10),
            'last_accessed_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'created_at' => now(),
        ];
    }

    public function fromCoreBlock(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => MemoryEmbedding::SOURCE_CORE_BLOCK,
        ]);
    }

    public function fromDocument(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => MemoryEmbedding::SOURCE_DOCUMENT,
        ]);
    }

    public function highImportance(): static
    {
        return $this->state(fn (array $attributes) => [
            'importance_score' => $this->faker->randomFloat(2, 0.8, 1.0),
        ]);
    }

    public function lowImportance(): static
    {
        return $this->state(fn (array $attributes) => [
            'importance_score' => $this->faker->randomFloat(2, 0.0, 0.2),
        ]);
    }
}
