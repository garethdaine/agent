<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentationEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentationEntry>
 */
class DocumentationEntryFactory extends Factory
{
    protected $model = DocumentationEntry::class;

    public function definition(): array
    {
        return [
            'domain' => $this->faker->randomElement(['platform', 'api', 'guide', 'reference']),
            'slug' => $this->faker->unique()->slug(3),
            'locale' => 'en',
            'title' => $this->faker->sentence(4),
            'summary' => $this->faker->sentence(),
            'section' => $this->faker->word(),
            'audience' => $this->faker->randomElement(['developer', 'admin', 'user']),
            'status' => 'published',
            'version' => '1.0',
            'tags' => [$this->faker->word()],
            'owner' => $this->faker->name(),
            'body_markdown' => $this->faker->paragraphs(3, true),
            'body_html' => '<p>'.$this->faker->paragraph().'</p>',
            'source_path' => 'docs/'.$this->faker->slug(2).'.md',
            'source_checksum' => hash('sha256', $this->faker->sentence()),
            'source_commit' => $this->faker->sha1(),
            'published_at' => now(),
            'last_reviewed_at' => null,
        ];
    }
}
