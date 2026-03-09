<?php

declare(strict_types=1);

namespace App\Actions\Documentation;

use App\Models\DocumentationEntry;

class FindDocumentationEntryAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, string $locale = 'en'): ?array
    {
        $normalizedSlug = trim($slug);
        if ($normalizedSlug === '') {
            return null;
        }

        $entry = DocumentationEntry::query()
            ->where('slug', $normalizedSlug)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->first();

        if ($entry === null) {
            return null;
        }

        return [
            'slug' => (string) $entry->slug,
            'title' => (string) $entry->title,
            'summary' => (string) ($entry->summary ?? ''),
            'section' => (string) ($entry->section ?? ''),
            'domain' => (string) $entry->domain,
            'body_html' => (string) ($entry->body_html ?? ''),
            'body_markdown' => (string) $entry->body_markdown,
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }
}
