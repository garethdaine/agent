<?php

declare(strict_types=1);

namespace App\Support\Documentation;

class CoverageAuditService
{
    public function __construct(
        private readonly DocsIngestionPipeline $pipeline
    ) {}

    /**
     * @return array{
     *   generated_at: string,
     *   totals: array{
     *     surfaces: int,
     *     surfaces_passed: int,
     *     coverage_percent: float,
     *     coverage_issues: int,
     *     link_orphan_issues: int
     *   },
     *   surfaces: array<int, array{
     *     id: string,
     *     label: string,
     *     passed: bool,
     *     checks: array{
     *       overview: bool,
     *       settings_detail: bool,
     *       example: bool,
     *       troubleshooting: bool,
     *       tooltip_links: bool
     *     },
     *     issues: array<int, string>
     *   }>,
     *   coverage_issues: array<int, string>,
     *   link_orphan_issues: array<int, string>
     * }
     */
    public function auditRepository(): array
    {
        $ingested = $this->pipeline->ingest();
        $requiredLocale = strtolower((string) config('docs_coverage.required_locale', 'en'));
        $requiredEntryStatus = strtolower((string) config('docs_coverage.required_entry_status', 'published'));

        $entries = array_values(array_filter(
            $ingested['entries'],
            fn (array $entry): bool => strtolower((string) ($entry['locale'] ?? '')) === $requiredLocale
        ));
        $fragments = array_values(array_filter(
            $ingested['fragments'],
            fn (array $fragment): bool => strtolower((string) ($fragment['locale'] ?? '')) === $requiredLocale
        ));

        $publishedEntries = array_values(array_filter(
            $entries,
            fn (array $entry): bool => strtolower((string) ($entry['status'] ?? '')) === $requiredEntryStatus
        ));
        $activeFragments = array_values(array_filter(
            $fragments,
            fn (array $fragment): bool => strtolower((string) ($fragment['status'] ?? 'published')) === 'published'
        ));

        $entriesBySlug = $this->indexEntriesBySlug($entries);
        $fragmentsByUiKey = $this->indexFragmentsByUiKey($activeFragments);

        $surfaceResults = [];
        $coverageIssues = [];
        $linkOrphanIssues = [];
        $surfacesPassed = 0;

        /** @var array<int, array<string, mixed>> $surfaceConfigs */
        $surfaceConfigs = config('docs_coverage.surfaces', []);

        foreach ($surfaceConfigs as $surfaceConfig) {
            $surfaceResult = $this->auditSurface(
                $surfaceConfig,
                $publishedEntries,
                $fragmentsByUiKey,
                $entriesBySlug
            );

            if ($surfaceResult['passed']) {
                $surfacesPassed++;
            }

            $surfaceResults[] = $surfaceResult;
            $coverageIssues = [...$coverageIssues, ...$surfaceResult['issues']];
            $linkOrphanIssues = [...$linkOrphanIssues, ...$surfaceResult['link_orphan_issues']];
        }

        $linkOrphanIssues = [
            ...$linkOrphanIssues,
            ...$this->auditFragmentLearnMoreLinks($activeFragments, $entriesBySlug, $requiredEntryStatus),
            ...$this->auditCriticalRoutes($publishedEntries),
        ];

        $coverageIssues = $this->normalizeIssues($coverageIssues);
        $linkOrphanIssues = $this->normalizeIssues($linkOrphanIssues);
        $surfaceCount = count($surfaceResults);
        $coveragePercent = $surfaceCount === 0
            ? 100.0
            : round(($surfacesPassed / $surfaceCount) * 100, 2);

        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'surfaces' => $surfaceCount,
                'surfaces_passed' => $surfacesPassed,
                'coverage_percent' => $coveragePercent,
                'coverage_issues' => count($coverageIssues),
                'link_orphan_issues' => count($linkOrphanIssues),
            ],
            'surfaces' => array_map(
                function (array $surface): array {
                    $surface['issues'] = $this->normalizeIssues($surface['issues']);
                    unset($surface['link_orphan_issues']);

                    return $surface;
                },
                $surfaceResults
            ),
            'coverage_issues' => $coverageIssues,
            'link_orphan_issues' => $linkOrphanIssues,
        ];
    }

    /**
     * @param  array<string, mixed>  $surfaceConfig
     * @param  array<int, array<string, mixed>>  $publishedEntries
     * @param  array<string, array<string, mixed>>  $fragmentsByUiKey
     * @param  array<string, array<string, mixed>>  $entriesBySlug
     * @return array{
     *   id: string,
     *   label: string,
     *   passed: bool,
     *   checks: array{
     *     overview: bool,
     *     settings_detail: bool,
     *     example: bool,
     *     troubleshooting: bool,
     *     tooltip_links: bool
     *   },
     *   issues: array<int, string>,
     *   link_orphan_issues: array<int, string>
     * }
     */
    private function auditSurface(
        array $surfaceConfig,
        array $publishedEntries,
        array $fragmentsByUiKey,
        array $entriesBySlug
    ): array {
        $id = trim((string) ($surfaceConfig['id'] ?? 'surface'));
        $label = trim((string) ($surfaceConfig['label'] ?? $id));

        $requiredRoutes = $this->normalizeStringList($surfaceConfig['required_routes'] ?? []);
        $requiredSettings = $this->normalizeSettingKeys($surfaceConfig['required_settings'] ?? []);
        $requiredTooltipUiKeys = $this->normalizeStringList($surfaceConfig['required_tooltip_ui_keys'] ?? []);

        $routeMatchedEntries = $this->entriesMatchingRoutes($publishedEntries, $requiredRoutes);
        $settingMatchedEntries = $this->entriesMatchingSettings($publishedEntries, $requiredSettings);
        $surfaceEntries = $this->mergeEntries($routeMatchedEntries, $settingMatchedEntries);

        $hasOverview = $surfaceEntries !== [];
        $hasSettingsDetail = $requiredSettings === [] || $settingMatchedEntries !== [];
        $hasExample = $this->entriesContainMarker($surfaceEntries, 'example');
        $hasTroubleshooting = $this->entriesContainMarker($surfaceEntries, 'troubleshooting');

        $missingTooltipUiKeys = [];
        $brokenTooltipLearnMoreLinks = [];
        foreach ($requiredTooltipUiKeys as $uiKey) {
            $fragment = $fragmentsByUiKey[$uiKey] ?? null;
            if ($fragment === null) {
                $missingTooltipUiKeys[] = $uiKey;
                continue;
            }

            $learnMoreSlug = trim((string) ($fragment['learn_more_slug'] ?? ''));
            if ($learnMoreSlug === '') {
                $brokenTooltipLearnMoreLinks[] = sprintf(
                    'Surface [%s] tooltip ui_key [%s] is missing learn_more_slug.',
                    $label,
                    $uiKey
                );
                continue;
            }

            $linkedEntry = $entriesBySlug[strtolower($learnMoreSlug)] ?? null;
            if ($linkedEntry === null) {
                $brokenTooltipLearnMoreLinks[] = sprintf(
                    'Surface [%s] tooltip ui_key [%s] references unresolved learn_more_slug [%s].',
                    $label,
                    $uiKey,
                    $learnMoreSlug
                );
                continue;
            }

            if (strtolower((string) ($linkedEntry['status'] ?? '')) === 'deprecated') {
                $brokenTooltipLearnMoreLinks[] = sprintf(
                    'Surface [%s] tooltip ui_key [%s] references deprecated learn_more_slug [%s].',
                    $label,
                    $uiKey,
                    $learnMoreSlug
                );
            }
        }

        $hasTooltipLinks = $missingTooltipUiKeys === [] && $brokenTooltipLearnMoreLinks === [];

        $issues = [];
        $linkOrphanIssues = [];

        if (! $hasOverview) {
            $issues[] = sprintf(
                'Surface [%s] is missing published overview documentation for required route/setting mappings.',
                $label
            );
        }

        if (! $hasSettingsDetail) {
            $issues[] = sprintf(
                'Surface [%s] is missing settings detail coverage for required setting keys: %s.',
                $label,
                implode(', ', $requiredSettings)
            );
        }

        if (! $hasExample) {
            $issues[] = sprintf(
                'Surface [%s] is missing example documentation section.',
                $label
            );
        }

        if (! $hasTroubleshooting) {
            $issues[] = sprintf(
                'Surface [%s] is missing troubleshooting guidance section.',
                $label
            );
        }

        if ($missingTooltipUiKeys !== []) {
            $message = sprintf(
                'Surface [%s] is missing required tooltip ui_key references: %s.',
                $label,
                implode(', ', $missingTooltipUiKeys)
            );
            $issues[] = $message;
            $linkOrphanIssues[] = $message;
        }

        foreach ($brokenTooltipLearnMoreLinks as $message) {
            $issues[] = $message;
            $linkOrphanIssues[] = $message;
        }

        return [
            'id' => $id,
            'label' => $label,
            'passed' => $issues === [],
            'checks' => [
                'overview' => $hasOverview,
                'settings_detail' => $hasSettingsDetail,
                'example' => $hasExample,
                'troubleshooting' => $hasTroubleshooting,
                'tooltip_links' => $hasTooltipLinks,
            ],
            'issues' => $issues,
            'link_orphan_issues' => $linkOrphanIssues,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function indexEntriesBySlug(array $entries): array
    {
        $indexed = [];

        foreach ($entries as $entry) {
            $slug = strtolower(trim((string) ($entry['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }

            $indexed[$slug] = $entry;
        }

        return $indexed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fragments
     * @return array<string, array<string, mixed>>
     */
    private function indexFragmentsByUiKey(array $fragments): array
    {
        $indexed = [];

        foreach ($fragments as $fragment) {
            $uiKey = strtolower(trim((string) ($fragment['ui_key'] ?? '')));
            if ($uiKey === '') {
                continue;
            }

            $indexed[$uiKey] = $fragment;
        }

        return $indexed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, string>  $requiredRoutes
     * @return array<int, array<string, mixed>>
     */
    private function entriesMatchingRoutes(array $entries, array $requiredRoutes): array
    {
        if ($requiredRoutes === []) {
            return [];
        }

        return array_values(array_filter($entries, function (array $entry) use ($requiredRoutes): bool {
            $entryRoutes = $this->normalizeStringList($entry['route_names'] ?? []);

            return array_intersect($requiredRoutes, $entryRoutes) !== [];
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, string>  $requiredSettings
     * @return array<int, array<string, mixed>>
     */
    private function entriesMatchingSettings(array $entries, array $requiredSettings): array
    {
        if ($requiredSettings === []) {
            return [];
        }

        return array_values(array_filter($entries, function (array $entry) use ($requiredSettings): bool {
            $entrySettings = $this->normalizeSettingKeys($entry['setting_keys'] ?? []);

            return array_intersect($requiredSettings, $entrySettings) !== [];
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $left
     * @param  array<int, array<string, mixed>>  $right
     * @return array<int, array<string, mixed>>
     */
    private function mergeEntries(array $left, array $right): array
    {
        $combined = [...$left, ...$right];
        $indexed = [];

        foreach ($combined as $entry) {
            $key = strtolower((string) ($entry['domain'] ?? '')).'|'.strtolower((string) ($entry['slug'] ?? ''));
            $indexed[$key] = $entry;
        }

        return array_values($indexed);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function entriesContainMarker(array $entries, string $markerType): bool
    {
        if ($entries === []) {
            return false;
        }

        $markers = $this->normalizeStringList(config("docs_coverage.required_content_markers.{$markerType}", []));
        if ($markers === []) {
            return true;
        }

        foreach ($entries as $entry) {
            $body = (string) ($entry['body_markdown'] ?? '');
            if ($this->containsAnyMarker($body, $markers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $markers
     */
    private function containsAnyMarker(string $body, array $markers): bool
    {
        $normalizedBody = strtolower($body);

        foreach ($markers as $marker) {
            if (str_contains($normalizedBody, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fragments
     * @param  array<string, array<string, mixed>>  $entriesBySlug
     * @return array<int, string>
     */
    private function auditFragmentLearnMoreLinks(array $fragments, array $entriesBySlug, string $requiredEntryStatus): array
    {
        $issues = [];

        foreach ($fragments as $fragment) {
            $uiKey = (string) ($fragment['ui_key'] ?? '');
            $learnMoreSlug = trim((string) ($fragment['learn_more_slug'] ?? ''));
            if ($learnMoreSlug === '') {
                continue;
            }

            $entry = $entriesBySlug[strtolower($learnMoreSlug)] ?? null;
            if ($entry === null) {
                $issues[] = sprintf(
                    'Tooltip ui_key [%s] references unresolved learn_more_slug [%s].',
                    $uiKey,
                    $learnMoreSlug
                );
                continue;
            }

            if (strtolower((string) ($entry['status'] ?? '')) === 'deprecated') {
                $issues[] = sprintf(
                    'Tooltip ui_key [%s] references deprecated learn_more_slug [%s].',
                    $uiKey,
                    $learnMoreSlug
                );
                continue;
            }

            if (strtolower((string) ($entry['status'] ?? '')) !== $requiredEntryStatus) {
                $issues[] = sprintf(
                    'Tooltip ui_key [%s] references learn_more_slug [%s] with non-published status [%s].',
                    $uiKey,
                    $learnMoreSlug,
                    (string) ($entry['status'] ?? 'unknown')
                );
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    private function auditCriticalRoutes(array $entries): array
    {
        $requiredRoutes = $this->normalizeStringList(config('docs_coverage.critical_routes', []));
        if ($requiredRoutes === []) {
            return [];
        }

        $documentedRoutes = [];
        foreach ($entries as $entry) {
            foreach ($this->normalizeStringList($entry['route_names'] ?? []) as $routeName) {
                $documentedRoutes[$routeName] = true;
            }
        }

        $missingRoutes = array_values(array_filter(
            $requiredRoutes,
            fn (string $routeName): bool => ! isset($documentedRoutes[$routeName])
        ));
        sort($missingRoutes);

        return array_map(
            static fn (string $routeName): string => sprintf(
                'Missing critical route docs mapping for route [%s].',
                $routeName
            ),
            $missingRoutes
        );
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = strtolower($trimmed);
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeSettingKeys(array $values): array
    {
        $keys = $this->normalizeStringList($values);

        return array_values(array_map(
            fn (string $key): string => $this->applySettingAlias($key),
            $keys
        ));
    }

    private function applySettingAlias(string $key): string
    {
        /** @var array<string, string> $aliases */
        $aliases = config('docs_coverage.setting_key_aliases', []);
        $lookupKey = strtolower($key);

        if (! isset($aliases[$lookupKey])) {
            return $lookupKey;
        }

        return strtolower(trim((string) $aliases[$lookupKey]));
    }

    /**
     * @param  array<int, string>  $issues
     * @return array<int, string>
     */
    private function normalizeIssues(array $issues): array
    {
        $normalized = [];
        foreach ($issues as $issue) {
            $trimmed = trim($issue);
            if ($trimmed === '') {
                continue;
            }
            $normalized[] = $trimmed;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
