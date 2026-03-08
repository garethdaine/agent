<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $domain
 * @property string $slug
 * @property string $locale
 * @property string $title
 * @property string|null $summary
 * @property string|null $section
 * @property string|null $audience
 * @property string $status
 * @property string|null $version
 * @property array|null $tags
 * @property string|null $owner
 * @property string $body_markdown
 * @property string|null $body_html
 * @property string|null $source_path
 * @property string|null $source_checksum
 * @property string|null $source_commit
 * @property \Carbon\CarbonInterface|null $published_at
 * @property \Carbon\CarbonInterface|null $last_reviewed_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocumentationFragment> $fragments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DocumentationLink> $links
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApiDocArtifact> $apiArtifacts
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class DocumentationEntry extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'domain',
        'slug',
        'locale',
        'title',
        'summary',
        'section',
        'audience',
        'status',
        'version',
        'tags',
        'owner',
        'body_markdown',
        'body_html',
        'source_path',
        'source_checksum',
        'source_commit',
        'published_at',
        'last_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function fragments(): HasMany
    {
        return $this->hasMany(DocumentationFragment::class, 'learn_more_entry_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentationLink::class, 'documentation_entry_id');
    }

    public function apiArtifacts(): HasMany
    {
        return $this->hasMany(ApiDocArtifact::class, 'documentation_entry_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('links:documentation_entry_id,route_name,setting_key');

        $routeNames = $this->links
            ->pluck('route_name')
            ->filter()
            ->values()
            ->all();

        $settingKeys = $this->links
            ->pluck('setting_key')
            ->filter()
            ->values()
            ->all();

        return [
            'id' => (string) $this->getScoutKey(),
            'domain' => (string) $this->domain,
            'title' => (string) $this->title,
            'summary' => (string) ($this->summary ?? ''),
            'body' => (string) $this->body_markdown,
            'tags' => is_array($this->tags) ? $this->tags : [],
            'section' => (string) ($this->section ?? ''),
            'route_names' => $routeNames,
            'setting_keys' => $settingKeys,
            'updated_at_timestamp' => (int) ($this->updated_at->timestamp ?? now()->timestamp),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function typesenseCollectionSchema(): array
    {
        return [
            'name' => $this->searchableAs(),
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'domain', 'type' => 'string'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'summary', 'type' => 'string', 'optional' => true],
                ['name' => 'body', 'type' => 'string', 'optional' => true],
                ['name' => 'tags', 'type' => 'string[]', 'optional' => true],
                ['name' => 'section', 'type' => 'string', 'optional' => true],
                ['name' => 'route_names', 'type' => 'string[]', 'optional' => true],
                ['name' => 'setting_keys', 'type' => 'string[]', 'optional' => true],
                ['name' => 'updated_at_timestamp', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'updated_at_timestamp',
        ];
    }
}
