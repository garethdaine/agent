<?php

declare(strict_types=1);

namespace App\Actions\Documentation;

use App\Models\DocumentationEntry;
use Illuminate\Support\Collection;

class ListDocumentationEntriesAction
{
    /**
     * @return Collection<int, array{slug: string, title: string, summary: string, section: string, domain: string, updated_at: string|null}>
     */
    public function execute(string $query = '', string $domain = '', string $section = '', string $locale = 'en', int $limit = 200): Collection
    {
        return DocumentationEntry::query() // @phpstan-ignore return.type
            ->where('locale', $locale)
            ->where('status', 'published')
            ->when($domain !== '', fn ($builder) => $builder->where('domain', $domain))
            ->when($section !== '', fn ($builder) => $builder->where('section', $section))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($nested) use ($query): void {
                    $nested->where('title', 'like', "%{$query}%")
                        ->orWhere('summary', 'like', "%{$query}%")
                        ->orWhere('slug', 'like', "%{$query}%")
                        ->orWhere('body_markdown', 'like', "%{$query}%");
                });
            })
            ->orderBy('section')
            ->orderBy('title')
            ->limit($limit)
            ->get(['slug', 'title', 'summary', 'section', 'domain', 'updated_at'])
            ->map(fn (DocumentationEntry $entry): array => [
                'slug' => (string) $entry->slug,
                'title' => (string) $entry->title,
                'summary' => (string) ($entry->summary ?? ''),
                'section' => (string) ($entry->section ?? ''),
                'domain' => (string) $entry->domain,
                'updated_at' => $entry->updated_at?->toIso8601String(),
            ]);
    }
}
