<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiDocArtifact;
use App\Models\DocumentationEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiDocArtifact>
 */
class ApiDocArtifactFactory extends Factory
{
    protected $model = ApiDocArtifact::class;

    public function definition(): array
    {
        return [
            'documentation_entry_id' => DocumentationEntry::factory(),
            'domain' => $this->faker->randomElement(['api', 'webhook', 'internal']),
            'operation_id' => $this->faker->unique()->slug(3),
            'http_method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'path' => '/api/v1/'.$this->faker->slug(2),
            'summary' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'section' => $this->faker->word(),
            'tags' => [$this->faker->word(), $this->faker->word()],
            'spec_version' => '3.0.0',
            'spec_checksum' => hash('sha256', $this->faker->sentence()),
            'linked_doc_slugs' => [],
            'published_at' => now(),
        ];
    }
}
