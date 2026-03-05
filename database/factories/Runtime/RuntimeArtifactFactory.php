<?php

namespace Database\Factories\Runtime;

use App\Enums\Runtime\RuntimeArtifactType;
use App\Models\Runtime\RuntimeArtifact;
use App\Models\Runtime\RuntimeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeArtifact>
 */
class RuntimeArtifactFactory extends Factory
{
    protected $model = RuntimeArtifact::class;

    public function definition(): array
    {
        return [
            'runtime_session_id' => RuntimeSession::factory(),
            'type' => RuntimeArtifactType::File,
            'path' => '/tmp/artifacts/'.$this->faker->uuid().'/'.$this->faker->word().'.'.$this->faker->fileExtension(),
            'metadata_json' => [
                'size' => $this->faker->numberBetween(100, 1000000),
                'mime_type' => $this->faker->mimeType(),
            ],
        ];
    }

    public function screenshot(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RuntimeArtifactType::Screenshot,
            'path' => '/tmp/artifacts/'.$this->faker->uuid().'/screenshot.png',
            'metadata_json' => [
                'width' => 1920,
                'height' => 1080,
                'format' => 'png',
            ],
        ]);
    }

    public function log(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RuntimeArtifactType::Log,
            'path' => '/tmp/artifacts/'.$this->faker->uuid().'/output.log',
            'metadata_json' => [
                'lines' => $this->faker->numberBetween(10, 1000),
            ],
        ]);
    }

    public function report(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RuntimeArtifactType::Report,
            'path' => '/tmp/artifacts/'.$this->faker->uuid().'/report.json',
            'metadata_json' => [
                'format' => 'json',
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
