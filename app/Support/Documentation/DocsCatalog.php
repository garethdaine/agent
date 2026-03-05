<?php

declare(strict_types=1);

namespace App\Support\Documentation;

class DocsCatalog
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $entries = [
        'overview' => [
            'slug' => 'overview',
            'title' => 'Documentation Overview',
            'summary' => 'Internal-only documentation entry point.',
            'section' => 'general',
            'domain' => 'product_doc',
            'updated_at' => '2026-03-02T00:00:00Z',
        ],
        'api-contracts' => [
            'slug' => 'api-contracts',
            'title' => 'API Contracts',
            'summary' => 'Read-only API contract references and usage notes.',
            'section' => 'api',
            'domain' => 'api_doc',
            'updated_at' => '2026-03-02T00:00:00Z',
        ],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $fragments = [
        'docs.overview' => [
            'ui_key' => 'docs.overview',
            'short_text' => 'Docs are internal-only and require authentication.',
            'long_text' => 'This helper text is served from the docs fragments read API.',
            'severity' => 'info',
            'learn_more_slug' => 'overview',
        ],
        'sessions.detail' => [
            'ui_key' => 'sessions.detail',
            'short_text' => 'Inspect session turns, tool calls, and approvals.',
            'severity' => 'info',
            'learn_more_slug' => 'overview',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(?string $query, ?string $domain = null, ?string $section = null, int $limit = 20): array
    {
        $query = trim((string) $query);

        $results = collect($this->entries)
            ->values()
            ->filter(function (array $entry) use ($domain, $section): bool {
                if (is_string($domain) && $domain !== '' && $entry['domain'] !== $domain) {
                    return false;
                }

                if (is_string($section) && $section !== '' && $entry['section'] !== $section) {
                    return false;
                }

                return true;
            })
            ->filter(function (array $entry) use ($query): bool {
                if ($query === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    (string) ($entry['title'] ?? ''),
                    (string) ($entry['summary'] ?? ''),
                    (string) ($entry['slug'] ?? ''),
                ]));

                return str_contains($haystack, strtolower($query));
            })
            ->take(max(1, min($limit, 50)))
            ->values()
            ->all();

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findEntry(string $slug): ?array
    {
        return $this->entries[$slug] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findFragment(string $uiKey): ?array
    {
        return $this->fragments[$uiKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function coverage(): array
    {
        $requiredSurfaces = 13;
        $documentedSurfaces = count($this->entries);

        return [
            'required_surfaces' => $requiredSurfaces,
            'documented_surfaces' => $documentedSurfaces,
            'coverage_percent' => round(($documentedSurfaces / $requiredSurfaces) * 100, 2),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
