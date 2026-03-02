<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class DocumentationFragment extends Model
{
    use HasFactory;
    use Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'route_names' => 'array',
            'setting_keys' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function learnMoreEntry(): BelongsTo
    {
        return $this->belongsTo(DocumentationEntry::class, 'learn_more_entry_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentationLink::class, 'documentation_fragment_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->getScoutKey(),
            'domain' => 'tooltip',
            'title' => (string) $this->ui_key,
            'summary' => (string) $this->short_text,
            'body' => (string) ($this->long_text ?? ''),
            'tags' => [],
            'section' => $this->sectionFromUiKey(),
            'route_names' => is_array($this->route_names) ? $this->route_names : [],
            'setting_keys' => is_array($this->setting_keys) ? $this->setting_keys : [],
            'updated_at_timestamp' => (int) ($this->updated_at?->timestamp ?? now()->timestamp),
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
                ['name' => 'summary', 'type' => 'string'],
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

    private function sectionFromUiKey(): string
    {
        $segment = explode('.', (string) $this->ui_key)[0] ?? 'general';

        return $segment !== '' ? $segment : 'general';
    }
}
