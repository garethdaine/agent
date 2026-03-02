<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Models\ApiDocArtifact;
use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ScoutDocsSearchIndexClient implements DocsSearchIndexClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?string $domain, ?string $section, int $limit): array
    {
        $safeLimit = max(1, min($limit, 50));
        $results = collect();

        if ($domain === null || $domain === 'product_doc' || $domain === 'api_doc') {
            $results = $results->concat($this->searchEntries($query, $domain, $section, $safeLimit));
        }

        if ($domain === null || $domain === 'tooltip') {
            $results = $results->concat($this->searchFragments($query, $section, $safeLimit));
        }

        if ($domain === null || $domain === 'api_doc') {
            $results = $results->concat($this->searchApiArtifacts($query, $section, $safeLimit));
        }

        return $results
            ->values()
            ->map(function (array $row, int $index): array {
                $row['__order'] = $index;

                return $row;
            })
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchEntries(string $query, ?string $domain, ?string $section, int $limit): Collection
    {
        $builder = DocumentationEntry::search($query);

        if ($domain !== null) {
            $builder->where('domain', $domain);
        }

        if ($section !== null) {
            $builder->where('section', $section);
        }

        return $builder
            ->take($limit)
            ->get()
            ->values()
            ->map(function (DocumentationEntry $entry, int $index): array {
                $entry->loadMissing('links:documentation_entry_id,route_name');

                $routeNames = $entry->links
                    ->pluck('route_name')
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'title' => $entry->title,
                    'snippet' => Str::limit($entry->summary ?: $entry->body_markdown, 180),
                    'domain' => $entry->domain,
                    'section' => $entry->section,
                    'route_names' => $routeNames,
                    'updated_at' => $entry->updated_at?->toIso8601String(),
                    '__order' => $index,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchFragments(string $query, ?string $section, int $limit): Collection
    {
        return DocumentationFragment::search($query)
            ->take($limit)
            ->get()
            ->values()
            ->map(function (DocumentationFragment $fragment, int $index): array {
                return [
                    'title' => $fragment->ui_key,
                    'snippet' => Str::limit($fragment->short_text ?: (string) $fragment->long_text, 180),
                    'domain' => 'tooltip',
                    'section' => $this->sectionFromUiKey($fragment->ui_key),
                    'route_names' => is_array($fragment->route_names) ? $fragment->route_names : [],
                    'updated_at' => $fragment->updated_at?->toIso8601String(),
                    '__order' => $index,
                ];
            })
            ->filter(function (array $row) use ($section): bool {
                if ($section === null) {
                    return true;
                }

                return ($row['section'] ?? null) === $section;
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchApiArtifacts(string $query, ?string $section, int $limit): Collection
    {
        $builder = ApiDocArtifact::search($query);

        if ($section !== null) {
            $builder->where('section', $section);
        }

        return $builder
            ->take($limit)
            ->get()
            ->values()
            ->map(function (ApiDocArtifact $artifact, int $index): array {
                $artifact->loadMissing('entry.links:documentation_entry_id,route_name');

                $routeNames = $artifact->entry?->links
                    ?->pluck('route_name')
                    ->filter()
                    ->values()
                    ->all() ?? [];

                return [
                    'title' => $artifact->summary ?: strtoupper($artifact->http_method).' '.$artifact->path,
                    'snippet' => Str::limit($artifact->description ?: (string) $artifact->summary, 180),
                    'domain' => 'api_doc',
                    'section' => $artifact->section,
                    'route_names' => $routeNames,
                    'updated_at' => $artifact->updated_at?->toIso8601String(),
                    '__order' => $index,
                ];
            });
    }

    private function sectionFromUiKey(string $uiKey): string
    {
        $segment = explode('.', $uiKey)[0] ?? 'general';

        return $segment !== '' ? $segment : 'general';
    }
}
