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
        'connectors.overview' => [
            'ui_key' => 'connectors.overview',
            'short_text' => 'Browse and manage connected third-party services.',
            'severity' => 'info',
        ],
        'deployments.overview' => [
            'ui_key' => 'deployments.overview',
            'short_text' => 'Deployment history and release status tracking.',
            'severity' => 'info',
        ],
        'sessions.overview' => [
            'ui_key' => 'sessions.overview',
            'short_text' => 'Active runtime sessions and connection status.',
            'severity' => 'info',
        ],
        'code-analysis.overview' => [
            'ui_key' => 'code-analysis.overview',
            'short_text' => 'Code analysis runs, findings, and quality metrics.',
            'severity' => 'info',
        ],
        'security.audit' => [
            'ui_key' => 'security.audit',
            'short_text' => 'Security audit findings and compliance status.',
            'severity' => 'info',
        ],
        'diagnostics' => [
            'ui_key' => 'diagnostics',
            'short_text' => 'System diagnostics and health check results.',
            'severity' => 'info',
        ],
        'services' => [
            'ui_key' => 'services',
            'short_text' => 'Registered services and their operational status.',
            'severity' => 'info',
        ],
        'logs' => [
            'ui_key' => 'logs',
            'short_text' => 'Application log stream and filtering controls.',
            'severity' => 'info',
        ],
        'audit.log' => [
            'ui_key' => 'audit.log',
            'short_text' => 'Audit trail of user and system actions.',
            'severity' => 'info',
        ],
        'memory.settings' => [
            'ui_key' => 'memory.settings',
            'short_text' => 'Memory system configuration and storage settings.',
            'severity' => 'info',
        ],
        'settings.tunnel' => [
            'ui_key' => 'settings.tunnel',
            'short_text' => 'Tunnel configuration for secure remote access.',
            'severity' => 'info',
        ],
        'settings.configuration' => [
            'ui_key' => 'settings.configuration',
            'short_text' => 'Application configuration and environment settings.',
            'severity' => 'info',
        ],
        'credentials' => [
            'ui_key' => 'credentials',
            'short_text' => 'Secret and credential management for integrations.',
            'severity' => 'info',
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
