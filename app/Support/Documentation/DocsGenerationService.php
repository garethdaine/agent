<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class DocsGenerationService
{
    private const AUTOGEN_START = '<!-- AUTO-GENERATED:START -->';

    private const AUTOGEN_END = '<!-- AUTO-GENERATED:END -->';

    public function __construct(
        private readonly DocsIngestionPipeline $pipeline
    ) {}

    /**
     * @return array{
     *   source: string,
     *   files_written: int,
     *   snapshot_files_updated: int,
     *   api_routes_indexed: int
     * }
     */
    public function generate(string $source): array
    {
        $normalizedSource = strtolower(trim($source));
        if ($normalizedSource !== 'repo') {
            throw DocumentationValidationException::fromErrors(
                ["Unsupported docs generation source '{$source}'."],
                'Docs generation failed'
            );
        }

        $ingested = $this->pipeline->ingest();
        $entries = $ingested['entries'] ?? [];

        $routes = collect(Route::getRoutes()->getRoutes());
        $routesByName = $this->mapRoutesByName($routes->all());
        $apiRoutes = $routes
            ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'agent/api/v1'))
            ->values()
            ->all();

        $filesWritten = 0;
        $snapshotFilesUpdated = 0;

        $inventoryPath = (string) config(
            'documentation.generation.output_paths.api_route_inventory',
            base_path('docs/api/reference/agent-v1-route-inventory.md')
        );
        $surfaceRefPath = (string) config(
            'documentation.generation.output_paths.api_surface_reference',
            base_path('docs/api/reference/agent-v1-surface-reference.md')
        );
        $coveragePath = (string) config(
            'documentation.generation.output_paths.interface_surface_coverage',
            base_path('docs/product/interface/surface-coverage.md')
        );

        $filesWritten += $this->writeIfChanged(
            $inventoryPath,
            $this->renderApiRouteInventory($apiRoutes, $entries)
        ) ? 1 : 0;

        $filesWritten += $this->writeIfChanged(
            $surfaceRefPath,
            $this->renderApiSurfaceReference($apiRoutes, $entries)
        ) ? 1 : 0;

        $filesWritten += $this->writeIfChanged(
            $coveragePath,
            $this->renderInterfaceCoverage($entries, $routesByName)
        ) ? 1 : 0;

        foreach ($entries as $entry) {
            $sourcePath = $this->resolveEntrySourcePath((string) ($entry['source_path'] ?? ''));
            if ($sourcePath === null || ! File::exists($sourcePath)) {
                continue;
            }

            $raw = (string) File::get($sourcePath);
            if (! preg_match('/\A(?<frontmatter>---\R.*?\R---\R?)(?<body>.*)\z/s', $raw, $matches)) {
                continue;
            }

            $body = (string) ($matches['body'] ?? '');
            $autogenBlock = $this->renderRuntimeSnapshotBlock($entry, $routesByName);

            $updatedBody = $this->replaceOrAppendAutogenBlock($body, $autogenBlock);
            $updated = (string) ($matches['frontmatter'] ?? '').$updatedBody;

            if ($updated !== $raw) {
                File::put($sourcePath, $updated);
                $snapshotFilesUpdated++;
            }
        }

        return [
            'source' => $normalizedSource,
            'files_written' => $filesWritten,
            'snapshot_files_updated' => $snapshotFilesUpdated,
            'api_routes_indexed' => count($apiRoutes),
        ];
    }

    /**
     * @param  array<int, IlluminateRoute>  $routes
     * @return array<string, IlluminateRoute>
     */
    private function mapRoutesByName(array $routes): array
    {
        $mapped = [];
        foreach ($routes as $route) {
            $name = $route->getName();
            if (is_string($name) && trim($name) !== '') {
                $mapped[$name] = $route;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<int, IlluminateRoute>  $apiRoutes
     */
    private function renderApiRouteInventory(array $apiRoutes, array $entries): string
    {
        usort($apiRoutes, fn (IlluminateRoute $left, IlluminateRoute $right): int => $left->uri() <=> $right->uri());

        $reviewedAt = $this->reviewedAtFromSlug($entries, 'agent-api-v1-route-inventory');
        $lines = [
            '---',
            'slug: agent-api-v1-route-inventory',
            'title: Agent API v1 Route Inventory',
            'summary: Complete route inventory for authenticated and webhook endpoints under /agent/api/v1.',
            'section: api',
            'audience: developer',
            'status: published',
            'version: "1.0.0"',
            'tags:',
            '  - api',
            '  - routes',
            '  - inventory',
            'owner: docs-team',
            'route_names:',
            '  - agent.api.connectors.slack.webhook',
            '  - agent.api.connectors.telegram.webhook',
            '  - agent.api.connectors.discord.webhook',
            '  - agent.api.connectors.whatsapp.webhook',
            'setting_keys:',
            '  - api.tokens.default_expiration_days',
            'feature_flags:',
            '  - docs_center_enabled',
            'locale: en',
            'reviewed_at: '.$reviewedAt,
            '---',
            '# Agent API v1 Route Inventory',
            '',
            'This inventory is generated from `php artisan route:list --path=agent/api/v1 --json` and tracks the current API surface from code.',
            '',
            '## Settings',
            '',
            'The API is versioned through `X-Agent-Api-Version: 1.0` and typically protected by `auth:sanctum` for authenticated routes.',
            '',
            '## Example',
            '',
            'After adding or modifying API routes, run `php artisan docs:generate` to regenerate this inventory before commit.',
            '',
            '## Troubleshooting',
            '',
            'If an expected endpoint is missing, verify route registration in `routes/api.php` and middleware feature gates.',
            '',
            '## Complete Route Table',
            '',
            '| Method | URI | Route Name | Controller | Middleware |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($apiRoutes as $route) {
            $methods = $this->methodString($route);
            $uri = $route->uri();
            $name = $route->getName() ?: '-';
            $controller = $this->controllerString($route);
            $middleware = implode(', ', array_map(static fn ($item): string => (string) $item, $route->gatherMiddleware()));
            $middleware = $middleware !== '' ? $middleware : '-';

            $lines[] = sprintf(
                '| %s | `%s` | `%s` | `%s` | `%s` |',
                $methods,
                $uri,
                $name,
                $controller,
                str_replace('|', '\\|', $middleware)
            );
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<int, IlluminateRoute>  $apiRoutes
     */
    private function renderApiSurfaceReference(array $apiRoutes, array $entries): string
    {
        $reviewedAt = $this->reviewedAtFromSlug($entries, 'agent-api-v1-surface-reference');
        $grouped = [];

        foreach ($apiRoutes as $route) {
            $uri = $route->uri();
            $segment = Str::of($uri)->after('agent/api/v1/')->before('/')->toString();
            $key = $segment !== '' ? $segment : 'root';

            if (! isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $route;
        }

        ksort($grouped);

        $lines = [
            '---',
            'slug: agent-api-v1-surface-reference',
            'title: Agent API v1 Surface Reference',
            'summary: Operator-focused API reference covering domain groups, auth model, rate limiting, and endpoint usage patterns.',
            'section: api',
            'audience: developer',
            'status: published',
            'version: "1.0.0"',
            'tags:',
            '  - api',
            '  - reference',
            '  - contracts',
            'owner: docs-team',
            'route_names:',
            '  - agent.api.connectors.slack.webhook',
            '  - agent.api.connectors.telegram.webhook',
            '  - agent.api.connectors.discord.webhook',
            '  - agent.api.connectors.whatsapp.webhook',
            'setting_keys:',
            '  - api.tokens.default_expiration_days',
            'feature_flags:',
            '  - docs_center_enabled',
            'locale: en',
            'reviewed_at: '.$reviewedAt,
            '---',
            '# Agent API v1 Surface Reference',
            '',
            'This guide is generated from live route registration and summarizes the API by operational domain.',
            '',
            '## Settings',
            '',
            '`api.tokens.default_expiration_days` determines baseline token lifespan for API integrations.',
            '',
            '| Setting | Purpose | Typical Value |',
            '| --- | --- | --- |',
            '| `api.tokens.default_expiration_days` | Default token expiry window | `30` |',
            '',
            '## Example',
            '',
            'When onboarding an integration, validate read endpoints first, then mutation endpoints with throttling/backoff handling.',
            '',
            '## Troubleshooting',
            '',
            '- `401/419`: verify Sanctum auth/session and CSRF boundaries.',
            '- `429`: respect endpoint throttles with retry backoff.',
            '- `503` on docs search: verify Typesense health and reindex queue.',
            '',
            '## Domain Groups',
            '',
        ];

        foreach ($grouped as $segment => $routes) {
            $label = $segment === 'root' ? 'Root' : Str::of($segment)->replace('-', ' ')->title()->toString();
            $lines[] = '### '.$label;
            $lines[] = '';
            $lines[] = sprintf('Registered endpoints: %d', count($routes));
            $lines[] = '';
            $lines[] = '| Method | URI | Route Name |';
            $lines[] = '| --- | --- | --- |';

            usort($routes, fn (IlluminateRoute $left, IlluminateRoute $right): int => $left->uri() <=> $right->uri());
            foreach ($routes as $route) {
                $lines[] = sprintf(
                    '| %s | `%s` | `%s` |',
                    $this->methodString($route),
                    $route->uri(),
                    $route->getName() ?: '-'
                );
            }

            $lines[] = '';
        }

        $lines[] = '## Related Docs';
        $lines[] = '';
        $lines[] = '- [API Token and Integration Flows](/docs/api-token-integration-flows)';
        $lines[] = '- [Agent API v1 Route Inventory](/docs/agent-api-v1-route-inventory)';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<string, IlluminateRoute>  $routesByName
     */
    private function renderInterfaceCoverage(array $entries, array $routesByName): string
    {
        $reviewedAt = $this->reviewedAtFromSlug($entries, 'interface-surface-coverage');
        /** @var array<int, array<string, mixed>> $surfaces */
        $surfaces = config('docs_coverage.surfaces', []);

        $lines = [
            '---',
            'slug: interface-surface-coverage',
            'title: Interface Surface Coverage',
            'summary: Master index of every major authenticated interface surface, what users can do, and where to find detailed docs.',
            'section: interface',
            'audience: operator',
            'status: published',
            'version: "1.0.0"',
            'tags:',
            '  - interface',
            '  - navigation',
            '  - coverage',
            'owner: docs-team',
            'route_names:',
            '  - dashboard',
            '  - docs.index',
            '  - agent.jobs.index',
            '  - agent.monitor.index',
            '  - tools.messenger.index',
            '  - tools.discovery.index',
            '  - tools.backups.settings',
            '  - tools.features.settings',
            '  - tools.memory.index',
            '  - agent.delegation.index',
            '  - org.index',
            '  - profile.show',
            'setting_keys:',
            '  - dashboard.default_range',
            '  - jobs.default_page_size',
            '  - monitor.poll_interval_seconds',
            '  - messenger.health_poll_interval_seconds',
            '  - discovery.default_provider',
            '  - backups.retention_days',
            '  - features.flags_refresh_minutes',
            '  - memory.retrieval_limit',
            '  - delegation.max_parallel_tasks',
            '  - org.default_execution_window_minutes',
            '  - profile.session_timeout_minutes',
            '  - api.tokens.default_expiration_days',
            'feature_flags:',
            '  - docs_center_enabled',
            '  - docs_search_enabled',
            '  - help_hint_enabled',
            'locale: en',
            'reviewed_at: '.$reviewedAt,
            '---',
            '# Interface Surface Coverage',
            '',
            'This page is generated from `config/docs_coverage.php`, route registration, and docs metadata.',
            '',
            '## Settings',
            '',
            'Coverage requires each surface to retain linked docs, route bindings, setting references, and tooltip linkage.',
            '',
            '## Product Navigation Coverage',
            '',
            '| Surface | Required Routes | Settings Keys | Linked Doc | Route Status |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($surfaces as $surface) {
            $label = (string) ($surface['label'] ?? 'Unknown');
            $requiredRoutes = $this->stringList($surface['required_routes'] ?? []);
            $requiredSettings = $this->stringList($surface['required_settings'] ?? []);

            $entry = $this->findBestEntryForSurface($entries, $requiredRoutes, $requiredSettings);
            $linkedDoc = $entry !== null
                ? sprintf('[%s](/docs/%s)', (string) $entry['title'], (string) $entry['slug'])
                : '_Missing_';

            $routeStatus = collect($requiredRoutes)
                ->map(function (string $name) use ($routesByName): string {
                    return isset($routesByName[$name]) ? 'ok' : 'missing';
                })
                ->contains('missing') ? 'partial' : 'ok';

            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $label,
                $requiredRoutes === [] ? '-' : implode(', ', array_map(fn (string $item): string => "`{$item}`", $requiredRoutes)),
                $requiredSettings === [] ? '-' : implode(', ', array_map(fn (string $item): string => "`{$item}`", $requiredSettings)),
                $linkedDoc,
                $routeStatus
            );
        }

        $lines[] = '';
        $lines[] = '## Example';
        $lines[] = '';
        $lines[] = 'After adding a new route to a covered surface, run `php artisan docs:generate` and confirm this table reflects the new binding.';
        $lines[] = '';
        $lines[] = '## Troubleshooting';
        $lines[] = '';
        $lines[] = '- If a linked doc is missing, ensure front matter includes the relevant `route_names` and `setting_keys`.';
        $lines[] = '- If route status is `partial`, verify route names in docs front matter match `routes/web.php` or `routes/api.php`.';
        $lines[] = '- Re-run `php artisan docs:coverage --fail-on-missing` to identify strict gate failures.';
        $lines[] = '';
        $lines[] = '## Related Docs';
        $lines[] = '';
        $lines[] = '- [Docs Center Overview](/docs/docs-center-overview)';
        $lines[] = '- [Agent API v1 Surface Reference](/docs/agent-api-v1-surface-reference)';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, IlluminateRoute>  $routesByName
     */
    private function renderRuntimeSnapshotBlock(array $entry, array $routesByName): string
    {
        $routeNames = $this->stringList($entry['route_names'] ?? []);
        $settingKeys = $this->stringList($entry['setting_keys'] ?? []);
        $featureFlags = $this->stringList($entry['feature_flags'] ?? []);

        $lines = [
            self::AUTOGEN_START,
            '## Runtime Contract Snapshot',
            '',
            'The block below is generated from code and front-matter metadata.',
            '',
            '### Verified Route Bindings',
            '',
            '| Route Name | Status | URI | Methods |',
            '| --- | --- | --- | --- |',
        ];

        if ($routeNames === []) {
            $lines[] = '| - | - | - | - |';
        } else {
            foreach ($routeNames as $routeName) {
                if (! isset($routesByName[$routeName])) {
                    $lines[] = sprintf('| `%s` | missing | - | - |', $routeName);

                    continue;
                }

                $route = $routesByName[$routeName];
                $lines[] = sprintf(
                    '| `%s` | ok | `%s` | `%s` |',
                    $routeName,
                    $route->uri(),
                    $this->methodString($route)
                );
            }
        }

        $lines[] = '';
        $lines[] = '### Referenced Settings Keys';
        $lines[] = '';
        if ($settingKeys === []) {
            $lines[] = '- None';
        } else {
            foreach ($settingKeys as $settingKey) {
                $lines[] = sprintf('- `%s`', $settingKey);
            }
        }

        $lines[] = '';
        $lines[] = '### Referenced Feature Flags';
        $lines[] = '';
        if ($featureFlags === []) {
            $lines[] = '- None';
        } else {
            foreach ($featureFlags as $featureFlag) {
                $lines[] = sprintf('- `%s`', $featureFlag);
            }
        }

        $lines[] = self::AUTOGEN_END;
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function replaceOrAppendAutogenBlock(string $body, string $block): string
    {
        $pattern = '/'.preg_quote(self::AUTOGEN_START, '/').'.*?'.preg_quote(self::AUTOGEN_END, '/').'\R?/s';

        if (preg_match($pattern, $body) === 1) {
            return (string) preg_replace($pattern, $block, $body, 1);
        }

        $trimmed = rtrim($body);
        if ($trimmed === '') {
            return $block;
        }

        return $trimmed."\n\n".$block;
    }

    private function writeIfChanged(string $path, string $content): bool
    {
        File::ensureDirectoryExists(dirname($path));

        $existing = File::exists($path) ? (string) File::get($path) : null;
        $normalized = rtrim($content)."\n";

        if ($existing === $normalized) {
            return false;
        }

        File::put($path, $normalized);

        return true;
    }

    private function resolveEntrySourcePath(string $sourcePath): ?string
    {
        $trimmed = trim($sourcePath);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, 'docs/')) {
            return base_path($trimmed);
        }

        if (str_starts_with($trimmed, 'product/')) {
            $root = (string) config('documentation.paths.product', base_path('docs/product'));
            $relative = ltrim(substr($trimmed, strlen('product/')), '/');

            return rtrim($root, '/').'/'.$relative;
        }

        if (str_starts_with($trimmed, 'api/')) {
            $root = (string) config('documentation.paths.api', base_path('docs/api'));
            $relative = ltrim(substr($trimmed, strlen('api/')), '/');

            return rtrim($root, '/').'/'.$relative;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $entries
     */
    private function reviewedAtFromSlug(array $entries, string $slug): string
    {
        foreach ($entries as $entry) {
            if ((string) ($entry['slug'] ?? '') !== $slug) {
                continue;
            }

            $value = $entry['last_reviewed_at'] ?? null;
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }
        }

        return now()->toDateString();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function stringList(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<int, string>  $requiredRoutes
     * @param  array<int, string>  $requiredSettings
     * @return array<string, mixed>|null
     */
    private function findBestEntryForSurface(array $entries, array $requiredRoutes, array $requiredSettings): ?array
    {
        $best = null;
        $bestScore = PHP_INT_MIN;

        foreach ($entries as $entry) {
            $entryRoutes = $this->stringList($entry['route_names'] ?? []);
            $entrySettings = $this->stringList($entry['setting_keys'] ?? []);

            $routeHits = count(array_intersect($requiredRoutes, $entryRoutes));
            $settingHits = count(array_intersect($requiredSettings, $entrySettings));
            if ($routeHits === 0 && $settingHits === 0) {
                continue;
            }

            $extraRoutes = max(0, count($entryRoutes) - $routeHits);
            $extraSettings = max(0, count($entrySettings) - $settingHits);
            $broadnessPenalty = $extraRoutes + $extraSettings;

            $score = ($routeHits * 4) + ($settingHits * 3) - $broadnessPenalty;

            if ($score > $bestScore) {
                $best = $entry;
                $bestScore = $score;
            }
        }

        if ($bestScore < 0) {
            return null;
        }

        return is_array($best) ? $best : null;
    }

    private function methodString(IlluminateRoute $route): string
    {
        $methods = collect($route->methods())
            ->filter(fn (string $method): bool => strtoupper($method) !== 'HEAD')
            ->values()
            ->all();

        return $methods !== [] ? implode(',', $methods) : 'GET';
    }

    private function controllerString(IlluminateRoute $route): string
    {
        $action = (string) $route->getActionName();
        if ($action === 'Closure') {
            return 'Closure';
        }

        if (str_contains($action, '@')) {
            return Str::afterLast($action, '\\');
        }

        return Str::afterLast($action, '\\');
    }
}
