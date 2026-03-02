<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class DocsSearchService
{
    public function __construct(
        private readonly DocsSearchIndexClient $indexClient
    ) {}

    /**
     * @return array<int, array{
     *   title: string,
     *   snippet: string,
     *   domain: string,
     *   section: string|null,
     *   slug: string|null,
     *   url: string|null,
     *   route_affinity: bool,
     *   updated_at: string|null
     * }>
     */
    public function search(string $query, ?string $domain, ?string $section, ?string $routeName, int $limit = 20): array
    {
        $normalizedQuery = trim($query);
        if ($normalizedQuery == '') {
            throw new InvalidArgumentException('Search query must not be empty.');
        }

        $normalizedDomain = $this->normalize($domain);
        $normalizedSection = $this->normalize($section);
        $normalizedRouteName = $this->normalize($routeName);

        $safeLimit = max(1, min($limit, 50));

        try {
            $rows = $this->indexClient->search(
                $normalizedQuery,
                $normalizedDomain,
                $normalizedSection,
                $safeLimit
            );
        } catch (Throwable $throwable) {
            throw DocsSearchUnavailableException::fromThrowable($throwable);
        }

        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = collect($rows)
            ->filter(fn (array $row): bool => $this->matchesDomain($row, $normalizedDomain))
            ->filter(fn (array $row): bool => $this->matchesSection($row, $normalizedSection))
            ->map(function (array $row, int $index) use ($normalizedRouteName): array {
                $routeNames = collect($row['route_names'] ?? [])
                    ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                    ->map(fn (string $value): string => trim($value))
                    ->values();

                $routeAffinity = $normalizedRouteName !== null && $routeNames->contains($normalizedRouteName);

                return [
                    'title' => (string) ($row['title'] ?? ''),
                    'snippet' => (string) ($row['snippet'] ?? ''),
                    'domain' => (string) ($row['domain'] ?? ''),
                    'section' => isset($row['section']) && $row['section'] !== '' ? (string) $row['section'] : null,
                    'slug' => isset($row['slug']) && is_string($row['slug']) && trim($row['slug']) !== '' ? trim((string) $row['slug']) : null,
                    'url' => isset($row['url']) && is_string($row['url']) && trim($row['url']) !== '' ? trim((string) $row['url']) : null,
                    'route_affinity' => $routeAffinity,
                    'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                    '__order' => (int) ($row['__order'] ?? $index),
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['route_affinity'] === $right['route_affinity']) {
                    return $left['__order'] <=> $right['__order'];
                }

                return $left['route_affinity'] ? -1 : 1;
            })
            ->take($safeLimit)
            ->values()
            ->map(function (array $row): array {
                unset($row['__order']);

                return $row;
            });

        return $collection->all();
    }

    private function normalize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesDomain(array $row, ?string $domain): bool
    {
        if ($domain === null) {
            return true;
        }

        return ($row['domain'] ?? null) === $domain;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesSection(array $row, ?string $section): bool
    {
        if ($section === null) {
            return true;
        }

        return ($row['section'] ?? null) === $section;
    }
}
