<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use App\Models\DocumentationLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationLink>
 */
class DocumentationLinkFactory extends Factory
{
    protected $model = DocumentationLink::class;

    public function definition(): array
    {
        return [
            'documentation_entry_id' => DocumentationEntry::factory(),
            'documentation_fragment_id' => DocumentationFragment::factory(),
            'route_name' => $this->faker->optional()->slug(2),
            'setting_key' => null,
            'feature_flag' => null,
        ];
    }
}
