<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationFragment>
 */
class DocumentationFragmentFactory extends Factory
{
    protected $model = DocumentationFragment::class;

    public function definition(): array
    {
        return [
            'ui_key' => $this->faker->unique()->slug(3),
            'locale' => 'en',
            'short_text' => $this->faker->sentence(),
            'long_text' => $this->faker->paragraph(),
            'learn_more_entry_id' => DocumentationEntry::factory(),
            'severity' => $this->faker->randomElement(['info', 'warning', 'error']),
            'feature_flag' => null,
            'status' => 'published',
            'route_names' => [],
            'setting_keys' => [],
            'source_path' => 'fragments/'.$this->faker->slug(2).'.md',
            'source_checksum' => hash('sha256', $this->faker->sentence()),
            'published_at' => now(),
        ];
    }
}
