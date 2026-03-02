<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use Illuminate\Support\Str;

class ExportService
{
    /**
     * @return array{markdown_export_path: string, json_export_path: string}
     */
    public function export(RepoAnalysisSession $session, RepoAnalysisReport $report): array
    {
        $relativeDirectory = $this->normalizedRelativeDirectory(
            (string) config('repo_analysis.exports.relative_directory', 'docs/discovery/repo-analysis')
        );
        $projectRoot = $this->resolvedProjectRoot((string) $session->project_directory);
        $directory = $projectRoot.'/'.$relativeDirectory;

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create export directory: %s', $directory));
        }

        [$markdownPath, $jsonPath] = $this->nextAvailablePair($directory, $this->sessionSlug($session));

        $payload = is_array($report->payload_json) ? $report->payload_json : [];

        file_put_contents($markdownPath, $this->markdown($session, $report, $payload));

        $encodedJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedJson === false) {
            throw new \RuntimeException('Unable to encode report payload as JSON.');
        }

        file_put_contents($jsonPath, $encodedJson.PHP_EOL);

        $report->markdown_export_path = $markdownPath;
        $report->json_export_path = $jsonPath;
        $report->save();

        return [
            'markdown_export_path' => $markdownPath,
            'json_export_path' => $jsonPath,
        ];
    }

    private function resolvedProjectRoot(string $projectDirectory): string
    {
        if ($projectDirectory === '' || ! str_starts_with($projectDirectory, '/')) {
            throw new \InvalidArgumentException('Export path policy violation: project directory must be an absolute path.');
        }

        $resolved = realpath($projectDirectory);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new \InvalidArgumentException('Export path policy violation: project directory could not be resolved.');
        }

        return rtrim($resolved, '/');
    }

    private function normalizedRelativeDirectory(string $relativeDirectory): string
    {
        $normalized = str_replace('\\', '/', trim($relativeDirectory));
        $normalized = trim($normalized, '/');

        if ($normalized === '' || str_starts_with($normalized, '/')) {
            throw new \InvalidArgumentException('Export path policy violation: export directory must be a relative path.');
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Export path policy violation: traversal segments are not allowed.');
            }
        }

        return $normalized;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function nextAvailablePair(string $directory, string $slug): array
    {
        for ($version = 1; $version <= 200; $version++) {
            $suffix = $version === 1 ? '' : '-v'.$version;
            $markdownPath = sprintf('%s/%s%s.md', $directory, $slug, $suffix);
            $jsonPath = sprintf('%s/%s%s.json', $directory, $slug, $suffix);

            if (! file_exists($markdownPath) && ! file_exists($jsonPath)) {
                return [$markdownPath, $jsonPath];
            }
        }

        $timestamp = time();

        return [
            sprintf('%s/%s-%s.md', $directory, $slug, $timestamp),
            sprintf('%s/%s-%s.json', $directory, $slug, $timestamp),
        ];
    }

    private function sessionSlug(RepoAnalysisSession $session): string
    {
        $base = trim((string) $session->name);
        if ($base === '') {
            $base = 'repo-analysis-session-'.$session->id;
        }

        $slug = Str::of($base)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        return $slug !== '' ? $slug : 'repo-analysis-session-'.$session->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdown(RepoAnalysisSession $session, RepoAnalysisReport $report, array $payload): string
    {
        $lines = [
            '# Repo Analysis Report',
            '',
            'Session: '.$session->id,
            'Report hash: '.(string) $report->report_hash,
            'Generated at: '.(string) data_get($payload, 'generated_at', ''),
            '',
        ];

        $repositoryProfile = data_get($payload, 'repository_profile', []);
        if (is_array($repositoryProfile) && $repositoryProfile !== []) {
            $lines = array_merge($lines, $this->renderRepositoryProfile($repositoryProfile));
        }

        $artifacts = data_get($payload, 'artifacts', []);
        if (is_array($artifacts) && $artifacts !== []) {
            $lines[] = '## Artifacts';
            $lines[] = '';

            foreach ($artifacts as $artifact) {
                if (! is_array($artifact)) {
                    continue;
                }

                $artifactKey = (string) ($artifact['artifact_key'] ?? '');
                $artifactType = (string) ($artifact['artifact_type'] ?? '');
                $contentHash = (string) ($artifact['content_hash'] ?? '');
                $lines[] = sprintf('- `%s` (%s) `%s`', $artifactKey, $artifactType, $contentHash);
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderRepositoryProfile(array $profile): array
    {
        $lines = ['## Repository Profile', ''];

        $overview = data_get($profile, 'overview', []);
        if (is_array($overview) && $overview !== []) {
            $lines[] = '### Overview';
            $lines[] = '';
            $lines[] = '- Project directory: `'.(string) ($overview['project_directory'] ?? '').'`';
            $lines[] = '- Snapshot hash: `'.(string) ($overview['snapshot_hash'] ?? '').'`';
            $lines[] = '- Files analyzed: '.(int) ($overview['snapshot_file_count'] ?? 0);

            $inferredStack = $this->stringList($overview['inferred_stack'] ?? []);
            if ($inferredStack !== []) {
                $lines[] = '- Inferred stack: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', $inferredStack));
            }

            $languageBreakdown = $overview['language_breakdown'] ?? [];
            if (is_array($languageBreakdown) && $languageBreakdown !== []) {
                $parts = [];
                foreach ($languageBreakdown as $language => $count) {
                    if (! is_string($language)) {
                        continue;
                    }

                    $parts[] = sprintf('%s (%d)', $language, (int) $count);
                }
                if ($parts !== []) {
                    $lines[] = '- Language distribution: '.implode(', ', $parts);
                }
            }

            $lines[] = '';
        }

        $lines = array_merge($lines, $this->renderDependencySection($profile));
        $lines = array_merge($lines, $this->renderStructureSection($profile));
        $lines = array_merge($lines, $this->renderBackendSection($profile));
        $lines = array_merge($lines, $this->renderFrontendSection($profile));
        $lines = array_merge($lines, $this->renderTestingSection($profile));
        $lines = array_merge($lines, $this->renderRiskSection($profile));
        $lines = array_merge($lines, $this->renderCoverageSection($profile));
        $lines = array_merge($lines, $this->renderGlossarySection($profile));

        $limitations = $this->stringList($profile['limitations'] ?? []);
        if ($limitations !== []) {
            $lines[] = '### Limitations';
            $lines[] = '';
            foreach ($limitations as $limitation) {
                $lines[] = '- '.$limitation;
            }
            $lines[] = '';
        }

        $warnings = $profile['warnings'] ?? [];
        if (is_array($warnings) && $warnings !== []) {
            $lines[] = '### Deterministic Parsing Warnings';
            $lines[] = '';
            foreach ($warnings as $warning) {
                if (! is_array($warning)) {
                    continue;
                }

                $code = (string) ($warning['code'] ?? 'warning');
                $message = (string) ($warning['message'] ?? '');
                $file = (string) ($warning['file'] ?? '');
                if ($message === '') {
                    continue;
                }

                $line = sprintf('- `%s`: %s', $code, $message);
                if ($file !== '') {
                    $line .= sprintf(' (`%s`)', $file);
                }

                $lines[] = $line;
            }
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderDependencySection(array $profile): array
    {
        $dependencies = data_get($profile, 'dependencies', []);
        if (! is_array($dependencies) || $dependencies === []) {
            return [];
        }

        $lines = ['### Dependencies', ''];

        $phpRuntime = $this->dependencyPairs(data_get($dependencies, 'php.runtime', []), 20);
        $phpDev = $this->dependencyPairs(data_get($dependencies, 'php.development', []), 20);
        $nodeRuntime = $this->dependencyPairs(data_get($dependencies, 'node.runtime', []), 20);
        $nodeDev = $this->dependencyPairs(data_get($dependencies, 'node.development', []), 20);

        $lines[] = sprintf('- PHP runtime dependencies: %d', (int) data_get($dependencies, 'php.runtime_count', 0));
        if ($phpRuntime !== []) {
            $lines[] = '  - sample: '.implode(', ', $phpRuntime);
        }

        $lines[] = sprintf('- PHP development dependencies: %d', (int) data_get($dependencies, 'php.development_count', 0));
        if ($phpDev !== []) {
            $lines[] = '  - sample: '.implode(', ', $phpDev);
        }

        $lines[] = sprintf('- Node runtime dependencies: %d', (int) data_get($dependencies, 'node.runtime_count', 0));
        if ($nodeRuntime !== []) {
            $lines[] = '  - sample: '.implode(', ', $nodeRuntime);
        }

        $lines[] = sprintf('- Node development dependencies: %d', (int) data_get($dependencies, 'node.development_count', 0));
        if ($nodeDev !== []) {
            $lines[] = '  - sample: '.implode(', ', $nodeDev);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderStructureSection(array $profile): array
    {
        $directories = data_get($profile, 'structure.top_level_directories', []);
        $notablePaths = $this->stringList(data_get($profile, 'structure.notable_paths', []));
        if (! is_array($directories) && $notablePaths === []) {
            return [];
        }

        $lines = ['### Codebase Structure', ''];

        if (is_array($directories) && $directories !== []) {
            $lines[] = '- Top-level directory distribution:';
            foreach ($directories as $directory) {
                if (! is_array($directory)) {
                    continue;
                }

                $name = (string) ($directory['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $lines[] = sprintf('  - `%s`: %d files', $name, (int) ($directory['file_count'] ?? 0));
            }
        }

        if ($notablePaths !== []) {
            $lines[] = '- Notable paths (sample): '.implode(', ', array_map(static fn (string $path): string => '`'.$path.'`', array_slice($notablePaths, 0, 20)));
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderBackendSection(array $profile): array
    {
        $backend = data_get($profile, 'backend', []);
        if (! is_array($backend) || $backend === []) {
            return [];
        }

        $lines = ['### Backend Surface', ''];
        $lines[] = sprintf('- Route files: %d', (int) ($backend['route_file_count'] ?? 0));
        $lines[] = sprintf('- Models: %d', (int) ($backend['model_count'] ?? 0));
        $lines[] = sprintf('- Migrations: %d', (int) ($backend['migration_count'] ?? 0));
        $lines[] = sprintf('- Jobs: %d', (int) ($backend['job_count'] ?? 0));
        $lines[] = sprintf('- Events: %d', (int) ($backend['event_count'] ?? 0));

        $models = $this->stringList($backend['models'] ?? []);
        if ($models !== []) {
            $lines[] = '- Model sample: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', array_slice($models, 0, 12)));
        }

        $jobs = $this->stringList($backend['jobs'] ?? []);
        if ($jobs !== []) {
            $lines[] = '- Job sample: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', array_slice($jobs, 0, 12)));
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderFrontendSection(array $profile): array
    {
        $frontend = data_get($profile, 'frontend', []);
        if (! is_array($frontend) || $frontend === []) {
            return [];
        }

        $lines = ['### Frontend Surface', ''];
        $lines[] = sprintf('- Entrypoint/module file count: %d', (int) ($frontend['entrypoint_count'] ?? 0));
        $lines[] = '- Package manifest detected: '.((bool) ($frontend['has_package_manifest'] ?? false) ? 'yes' : 'no');

        $entrypoints = $this->stringList($frontend['entrypoints'] ?? []);
        if ($entrypoints !== []) {
            $lines[] = '- Entrypoint sample: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', array_slice($entrypoints, 0, 20)));
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderTestingSection(array $profile): array
    {
        $testing = data_get($profile, 'testing', []);
        if (! is_array($testing) || $testing === []) {
            return [];
        }

        $lines = ['### Testing Surface', ''];
        $lines[] = sprintf('- Test file count: %d', (int) ($testing['test_file_count'] ?? 0));

        $testFiles = $this->stringList($testing['test_files'] ?? []);
        if ($testFiles !== []) {
            $lines[] = '- Test file sample: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', array_slice($testFiles, 0, 20)));
        }

        $warningCodes = $this->stringList($testing['warnings'] ?? []);
        if ($warningCodes !== []) {
            $lines[] = '- Test warnings: '.implode(', ', array_map(static fn (string $code): string => '`'.$code.'`', $warningCodes));
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderRiskSection(array $profile): array
    {
        $risk = data_get($profile, 'risk_hotspots', []);
        if (! is_array($risk) || $risk === []) {
            return [];
        }

        $lines = ['### Risk Hotspots', ''];
        $lines[] = sprintf('- Hotspot file count: %d', (int) ($risk['hotspot_count'] ?? 0));

        $hotspots = $this->stringList($risk['hotspot_files'] ?? []);
        if ($hotspots !== []) {
            $lines[] = '- Hotspot sample: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', array_slice($hotspots, 0, 20)));
        }

        $warningCodes = $this->stringList($risk['warnings'] ?? []);
        if ($warningCodes !== []) {
            $lines[] = '- Risk warnings: '.implode(', ', array_map(static fn (string $code): string => '`'.$code.'`', $warningCodes));
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderCoverageSection(array $profile): array
    {
        $coverage = data_get($profile, 'coverage_gate', []);
        if (! is_array($coverage) || $coverage === []) {
            return [];
        }

        $lines = ['### Coverage Gate', ''];
        $lines[] = '- Passed: '.((bool) ($coverage['passed'] ?? false) ? 'yes' : 'no');
        $lines[] = sprintf(
            '- Tasks completed: %d / %d',
            (int) ($coverage['completed_task_count'] ?? 0),
            (int) ($coverage['task_count'] ?? 0)
        );

        $required = $this->stringList($coverage['required_artifact_classes'] ?? []);
        if ($required !== []) {
            $lines[] = '- Required artifact classes: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', $required));
        }

        $missing = $this->stringList($coverage['missing_artifact_classes'] ?? []);
        if ($missing !== []) {
            $lines[] = '- Missing artifact classes: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', $missing));
        }

        $blocking = $this->stringList($coverage['blocking_failure_codes'] ?? []);
        if ($blocking !== []) {
            $lines[] = '- Blocking failures: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', $blocking));
        }

        $warningCodes = $this->stringList($coverage['warning_codes'] ?? []);
        if ($warningCodes !== []) {
            $lines[] = '- Coverage warnings: '.implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', $warningCodes));
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, string>
     */
    private function renderGlossarySection(array $profile): array
    {
        $glossary = data_get($profile, 'glossary', []);
        if (! is_array($glossary) || $glossary === []) {
            return [];
        }

        $lines = ['### Repo Analysis Glossary', ''];

        foreach (['task_graph', 'coverage_gate', 'artifacts'] as $key) {
            $value = trim((string) ($glossary[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $label = str_replace('_', ' ', $key);
            $lines[] = sprintf('- %s: %s', ucfirst($label), $value);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $items = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $items[] = $text;
        }

        $items = array_values(array_unique($items));
        sort($items, SORT_STRING);

        return $items;
    }

    /**
     * @param  mixed  $pairs
     * @return array<int, string>
     */
    private function dependencyPairs(mixed $pairs, int $limit): array
    {
        if (! is_array($pairs)) {
            return [];
        }

        $formatted = [];
        foreach (array_slice($pairs, 0, max(1, $limit)) as $pair) {
            if (! is_array($pair)) {
                continue;
            }

            $name = trim((string) ($pair['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $version = trim((string) ($pair['version'] ?? ''));
            $formatted[] = $version !== ''
                ? sprintf('`%s` (%s)', $name, $version)
                : sprintf('`%s`', $name);
        }

        return $formatted;
    }
}
