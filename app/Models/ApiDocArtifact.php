<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $documentation_entry_id
 * @property string $domain
 * @property string $operation_id
 * @property string $http_method
 * @property string $path
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $section
 * @property array|null $tags
 * @property string|null $spec_version
 * @property string|null $spec_checksum
 * @property array|null $linked_doc_slugs
 * @property \Carbon\CarbonInterface|null $published_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read \App\Models\DocumentationEntry|null $entry
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ApiDocArtifact extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'documentation_entry_id',
        'domain',
        'operation_id',
        'http_method',
        'path',
        'summary',
        'description',
        'section',
        'tags',
        'spec_version',
        'spec_checksum',
        'linked_doc_slugs',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'linked_doc_slugs' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DocumentationEntry::class, 'documentation_entry_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('entry.links:documentation_entry_id,route_name,setting_key');

        $routeNames = $this->entry?->links
            ?->pluck('route_name')
            ->filter()
            ->values()
            ->all() ?? [];

        $settingKeys = $this->entry?->links
            ?->pluck('setting_key')
            ->filter()
            ->values()
            ->all() ?? [];

        return [
            'id' => (string) $this->getScoutKey(),
            'domain' => 'api_doc',
            'title' => (string) ($this->summary ?: strtoupper($this->http_method).' '.$this->path),
            'summary' => (string) ($this->summary ?? ''),
            'body' => (string) ($this->description ?? ''),
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
