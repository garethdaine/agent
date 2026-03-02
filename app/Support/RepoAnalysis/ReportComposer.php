<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ReportComposer
{
    /**
     * @param  array<string, mixed>  $coverageSummary
     * @return array{
     *   report_version: string,
     *   report_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>,
     *   payload: array<int, array{artifact_key: string, artifact_type: string, content_hash: string}>
     * }
     */
    public function compose(RepoAnalysisSession $session, array $coverageSummary): array
    {
        /** @var array<int, array{
         *   artifact_key: string,
         *   artifact_type: string,
         *   content_hash: string,
         *   payload_json: array<string, mixed>,
         *   metadata_json: array<string, mixed>
         * }> $artifactRecords
         */
        $artifactRecords = RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $session->id)
            ->select(['artifact_key', 'artifact_type', 'content_hash', 'payload_json', 'metadata_json'])
            ->orderBy('artifact_key')
            ->get()
            ->map(static fn ($artifact): array => [
                'artifact_key' => (string) $artifact->artifact_key,
                'artifact_type' => (string) $artifact->artifact_type,
                'content_hash' => (string) $artifact->content_hash,
                'payload_json' => is_array($artifact->payload_json) ? $artifact->payload_json : [],
                'metadata_json' => is_array($artifact->metadata_json) ? $artifact->metadata_json : [],
            ])
            ->all();

        $artifacts = array_map(static fn (array $artifact): array => [
            'artifact_key' => $artifact['artifact_key'],
            'artifact_type' => $artifact['artifact_type'],
            'content_hash' => $artifact['content_hash'],
        ], $artifactRecords);

        $hashInput = [
            'snapshot_hash' => (string) $session->snapshot_hash,
            'artifacts' => array_map(static fn (array $artifact): array => [
                'artifact_key' => $artifact['artifact_key'],
                'content_hash' => $artifact['content_hash'],
            ], $artifacts),
        ];

        $reportHash = hash('sha256', $this->canonicalJson($hashInput));
        $repositoryProfile = $this->buildRepositoryProfile($session, $artifactRecords, $coverageSummary);

        $payload = [
            'session_id' => $session->id,
            'snapshot_hash' => (string) $session->snapshot_hash,
            'artifact_count' => count($artifacts),
            'artifacts' => $artifacts,
            'coverage' => $coverageSummary,
            'repository_profile' => $repositoryProfile,
            'generated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        return [
            'report_version' => '1.0.0',
            'report_hash' => $reportHash,
            'payload_json' => $payload,
            'metadata_json' => [
                'artifact_hash_count' => count($artifacts),
                'hash_input' => $hashInput,
                'repository_profile_warnings' => $repositoryProfile['warnings'] ?? [],
            ],
            'payload' => $artifacts,
        ];
    }

    /**
     * @param  array<int, array{
     *   artifact_key: string,
     *   artifact_type: string,
     *   content_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>
     * }>  $artifactRecords
     * @param  array<string, mixed>  $coverageSummary
     * @return array<string, mixed>
     */
    private function buildRepositoryProfile(
        RepoAnalysisSession $session,
        array $artifactRecords,
        array $coverageSummary,
    ): array {
        $warnings = [];
        $artifactByType = $this->artifactByType($artifactRecords);

        $snapshotManifest = [];
        if (isset($artifactByType['snapshot_manifest'])) {
            $snapshotManifest = $artifactByType['snapshot_manifest']['payload_json'];
        } else {
            foreach ($artifactRecords as $artifactRecord) {
                if ($artifactRecord['artifact_key'] === 'snapshot.manifest.json') {
                    $snapshotManifest = $artifactRecord['payload_json'];
                    break;
                }
            }
        }

        $manifestFiles = is_array($snapshotManifest['files'] ?? null)
            ? $snapshotManifest['files']
            : [];
        $paths = $this->manifestPaths($manifestFiles);
        $fileIndex = $this->manifestFileIndex($manifestFiles);
        $projectRoot = $this->resolvedProjectRoot((string) $session->project_directory);

        $composerJson = $this->readSnapshotLockedJson($projectRoot, $fileIndex, 'composer.json', $warnings);
        $packageJson = $this->readSnapshotLockedJson($projectRoot, $fileIndex, 'package.json', $warnings);

        $dependencyPayload = data_get($artifactByType, 'dependency_manifest.payload_json', []);
        $routePayload = data_get($artifactByType, 'laravel_routes.payload_json', []);
        $modelPayload = data_get($artifactByType, 'laravel_models_migrations.payload_json', []);
        $queuePayload = data_get($artifactByType, 'queue_jobs_events.payload_json', []);
        $frontendPayload = data_get($artifactByType, 'frontend_module_graph.payload_json', []);
        $testPayload = data_get($artifactByType, 'test_coverage_map.payload_json', []);
        $riskPayload = data_get($artifactByType, 'risk_hotspot.payload_json', []);

        $languageBreakdown = $this->languageBreakdown($paths);
        $topDirectories = $this->topLevelDistribution($paths);
        $dependencySummary = $this->dependencySummary($composerJson, $packageJson);

        $stackSignals = [
            ...$this->inferredStackFromDependencies($composerJson, $packageJson),
            ...$this->inferredStackFromArtifacts($dependencyPayload, $routePayload, $frontendPayload),
        ];
        $stackSignals = array_values(array_unique($stackSignals));
        sort($stackSignals, SORT_STRING);

        return [
            'overview' => [
                'session_name' => (string) ($session->name ?? ''),
                'project_directory' => (string) $session->project_directory,
                'snapshot_hash' => (string) $session->snapshot_hash,
                'snapshot_file_count' => count($paths),
                'inferred_stack' => $stackSignals,
                'language_breakdown' => $languageBreakdown,
            ],
            'dependencies' => $dependencySummary,
            'structure' => [
                'top_level_directories' => $topDirectories,
                'notable_paths' => $this->sampleList($paths, 30),
            ],
            'backend' => [
                'route_files' => $this->sampleList($this->stringList($routePayload['route_files'] ?? []), 30),
                'route_file_count' => (int) ($routePayload['route_file_count'] ?? 0),
                'models' => $this->basenameList($this->stringList($modelPayload['model_files'] ?? []), 30),
                'model_count' => (int) ($modelPayload['model_count'] ?? 0),
                'migrations' => $this->basenameList($this->stringList($modelPayload['migration_files'] ?? []), 30),
                'migration_count' => (int) ($modelPayload['migration_count'] ?? 0),
                'jobs' => $this->basenameList($this->stringList($queuePayload['job_files'] ?? []), 30),
                'job_count' => (int) ($queuePayload['job_count'] ?? 0),
                'events' => $this->basenameList($this->stringList($queuePayload['event_files'] ?? []), 30),
                'event_count' => (int) ($queuePayload['event_count'] ?? 0),
            ],
            'frontend' => [
                'entrypoints' => $this->sampleList($this->stringList($frontendPayload['entrypoints'] ?? []), 40),
                'entrypoint_count' => (int) ($frontendPayload['entrypoint_count'] ?? 0),
                'has_package_manifest' => (bool) ($frontendPayload['has_package_manifest'] ?? false),
            ],
            'testing' => [
                'test_file_count' => (int) ($testPayload['test_file_count'] ?? 0),
                'test_files' => $this->sampleList($this->stringList($testPayload['test_files'] ?? []), 40),
                'warnings' => $this->warningCodes($testPayload['warnings'] ?? []),
            ],
            'risk_hotspots' => [
                'hotspot_count' => (int) ($riskPayload['hotspot_count'] ?? 0),
                'hotspot_files' => $this->sampleList($this->stringList($riskPayload['hotspot_files'] ?? []), 40),
                'warnings' => $this->warningCodes($riskPayload['warnings'] ?? []),
            ],
            'coverage_gate' => [
                'passed' => (bool) ($coverageSummary['passed'] ?? false),
                'blocking_failure_codes' => $this->blockingFailureCodes($coverageSummary['blocking_failures'] ?? []),
                'warning_codes' => $this->warningCodes($coverageSummary['warnings'] ?? []),
                'required_artifact_classes' => $this->stringList($coverageSummary['required_artifact_classes'] ?? []),
                'missing_artifact_classes' => $this->stringList($coverageSummary['missing_artifact_classes'] ?? []),
                'task_count' => (int) ($coverageSummary['task_count'] ?? 0),
                'completed_task_count' => (int) ($coverageSummary['completed_task_count'] ?? 0),
            ],
            'glossary' => [
                'task_graph' => 'Deterministic analyzer DAG generated in phase 2, with dependency-ordered tasks executed in phase 3.',
                'coverage_gate' => 'Phase 4 validation that blocks completion if required artifact classes are missing or critical task failures exist.',
                'artifacts' => 'Versioned outputs from snapshot/analyzers/coverage/reporting, each identified by artifact key and content hash.',
            ],
            'limitations' => [
                'Analysis is static and deterministic; it summarizes repository structure and code surfaces without executing application runtime flows.',
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array{
     *   artifact_key: string,
     *   artifact_type: string,
     *   content_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>
     * }>  $artifactRecords
     * @return array<string, array{
     *   artifact_key: string,
     *   artifact_type: string,
     *   content_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>
     * }>
     */
    private function artifactByType(array $artifactRecords): array
    {
        $indexed = [];

        foreach ($artifactRecords as $artifactRecord) {
            $type = trim($artifactRecord['artifact_type']);
            if ($type === '' || isset($indexed[$type])) {
                continue;
            }

            $indexed[$type] = $artifactRecord;
        }

        ksort($indexed, SORT_STRING);

        return $indexed;
    }

    /**
     * @param  array<int, mixed>  $manifestFiles
     * @return array<int, string>
     */
    private function manifestPaths(array $manifestFiles): array
    {
        $paths = [];
        foreach ($manifestFiles as $manifestFile) {
            if (! is_array($manifestFile)) {
                continue;
            }

            $path = $manifestFile['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }

            $paths[] = str_replace('\\', '/', $path);
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * @param  array<int, mixed>  $manifestFiles
     * @return array<string, array{content_hash: string, size_bytes: int|null}>
     */
    private function manifestFileIndex(array $manifestFiles): array
    {
        $index = [];
        foreach ($manifestFiles as $manifestFile) {
            if (! is_array($manifestFile)) {
                continue;
            }

            $path = $manifestFile['path'] ?? null;
            $contentHash = $manifestFile['content_hash'] ?? null;
            if (! is_string($path) || $path === '' || ! is_string($contentHash) || $contentHash === '') {
                continue;
            }

            $normalizedPath = str_replace('\\', '/', $path);
            $sizeBytes = $manifestFile['size_bytes'] ?? null;
            $index[$normalizedPath] = [
                'content_hash' => $contentHash,
                'size_bytes' => is_int($sizeBytes) ? $sizeBytes : null,
            ];
        }

        ksort($index, SORT_STRING);

        return $index;
    }

    private function resolvedProjectRoot(string $projectDirectory): string
    {
        if ($projectDirectory === '' || ! str_starts_with($projectDirectory, '/')) {
            return '';
        }

        $resolved = realpath($projectDirectory);

        return is_string($resolved) && is_dir($resolved)
            ? rtrim($resolved, '/')
            : '';
    }

    /**
     * @param  array<string, array{content_hash: string, size_bytes: int|null}>  $fileIndex
     * @param  array<int, array{code: string, message: string, file?: string}>  &$warnings
     * @return array<string, mixed>|null
     */
    private function readSnapshotLockedJson(
        string $projectRoot,
        array $fileIndex,
        string $path,
        array &$warnings,
    ): ?array {
        $text = $this->readSnapshotLockedText($projectRoot, $fileIndex, $path, $warnings);
        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            $warnings[] = [
                'code' => 'json_parse_failed',
                'message' => sprintf('Unable to parse %s as JSON.', $path),
                'file' => $path,
            ];

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, array{content_hash: string, size_bytes: int|null}>  $fileIndex
     * @param  array<int, array{code: string, message: string, file?: string}>  &$warnings
     */
    private function readSnapshotLockedText(
        string $projectRoot,
        array $fileIndex,
        string $path,
        array &$warnings,
    ): ?string {
        $normalizedPath = str_replace('\\', '/', trim($path));
        $expected = $fileIndex[$normalizedPath] ?? null;
        if (! is_array($expected)) {
            return null;
        }

        if ($projectRoot === '') {
            $warnings[] = [
                'code' => 'project_root_unresolved',
                'message' => 'Project root could not be resolved during report composition.',
            ];

            return null;
        }

        $absolutePath = $projectRoot.'/'.$normalizedPath;
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            $warnings[] = [
                'code' => 'snapshot_file_unreadable',
                'message' => sprintf('Expected snapshot file is not readable: %s', $normalizedPath),
                'file' => $normalizedPath,
            ];

            return null;
        }

        $actualHash = hash_file('sha256', $absolutePath);
        if (! is_string($actualHash) || $actualHash !== $expected['content_hash']) {
            $warnings[] = [
                'code' => 'snapshot_file_hash_mismatch',
                'message' => sprintf('File changed since snapshot and was excluded from deterministic parsing: %s', $normalizedPath),
                'file' => $normalizedPath,
            ];

            return null;
        }

        $contents = file_get_contents($absolutePath);
        if (! is_string($contents)) {
            $warnings[] = [
                'code' => 'snapshot_file_read_failed',
                'message' => sprintf('Unable to read expected snapshot file: %s', $normalizedPath),
                'file' => $normalizedPath,
            ];

            return null;
        }

        return $contents;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<string, int>
     */
    private function languageBreakdown(array $paths): array
    {
        $extensionMap = [
            'php' => 'PHP',
            'js' => 'JavaScript',
            'mjs' => 'JavaScript',
            'cjs' => 'JavaScript',
            'ts' => 'TypeScript',
            'tsx' => 'TypeScript',
            'vue' => 'Vue',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'sass' => 'Sass',
            'json' => 'JSON',
            'yml' => 'YAML',
            'yaml' => 'YAML',
            'md' => 'Markdown',
            'markdown' => 'Markdown',
            'blade.php' => 'Blade',
        ];

        $counts = [];
        foreach ($paths as $path) {
            $normalizedPath = strtolower($path);
            $label = null;

            if (str_ends_with($normalizedPath, '.blade.php')) {
                $label = 'Blade';
            } else {
                $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
                if ($extension !== '' && isset($extensionMap[$extension])) {
                    $label = $extensionMap[$extension];
                }
            }

            if ($label === null) {
                continue;
            }

            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts, SORT_NUMERIC);

        return $counts;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{name: string, file_count: int}>
     */
    private function topLevelDistribution(array $paths): array
    {
        $counts = [];
        foreach ($paths as $path) {
            $segment = Str::before($path, '/');
            if ($segment === '') {
                $segment = '(root)';
            }

            $counts[$segment] = ($counts[$segment] ?? 0) + 1;
        }

        arsort($counts, SORT_NUMERIC);

        $distribution = [];
        foreach (array_slice($counts, 0, 20, true) as $name => $fileCount) {
            $distribution[] = [
                'name' => $name,
                'file_count' => $fileCount,
            ];
        }

        return $distribution;
    }

    /**
     * @param  array<string, mixed>|null  $composerJson
     * @param  array<string, mixed>|null  $packageJson
     * @return array<string, mixed>
     */
    private function dependencySummary(?array $composerJson, ?array $packageJson): array
    {
        $phpRuntime = $this->dependencyPairs($composerJson['require'] ?? []);
        $phpDev = $this->dependencyPairs($composerJson['require-dev'] ?? []);
        $nodeRuntime = $this->dependencyPairs($packageJson['dependencies'] ?? []);
        $nodeDev = $this->dependencyPairs($packageJson['devDependencies'] ?? []);

        return [
            'php' => [
                'runtime_count' => count($phpRuntime),
                'runtime' => array_slice($phpRuntime, 0, 60),
                'development_count' => count($phpDev),
                'development' => array_slice($phpDev, 0, 60),
            ],
            'node' => [
                'runtime_count' => count($nodeRuntime),
                'runtime' => array_slice($nodeRuntime, 0, 60),
                'development_count' => count($nodeDev),
                'development' => array_slice($nodeDev, 0, 60),
            ],
        ];
    }

    /**
     * @param  mixed  $dependencies
     * @return array<int, array{name: string, version: string}>
     */
    private function dependencyPairs(mixed $dependencies): array
    {
        if (! is_array($dependencies)) {
            return [];
        }

        $pairs = [];
        foreach ($dependencies as $name => $version) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $pairs[] = [
                'name' => trim($name),
                'version' => is_scalar($version) ? trim((string) $version) : '',
            ];
        }

        usort($pairs, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $pairs;
    }

    /**
     * @param  array<string, mixed>|null  $composerJson
     * @param  array<string, mixed>|null  $packageJson
     * @return array<int, string>
     */
    private function inferredStackFromDependencies(?array $composerJson, ?array $packageJson): array
    {
        $signals = [];
        $composerDeps = array_keys(is_array($composerJson['require'] ?? null) ? $composerJson['require'] : []);
        $composerDevDeps = array_keys(is_array($composerJson['require-dev'] ?? null) ? $composerJson['require-dev'] : []);
        $packageDeps = array_keys(is_array($packageJson['dependencies'] ?? null) ? $packageJson['dependencies'] : []);
        $packageDevDeps = array_keys(is_array($packageJson['devDependencies'] ?? null) ? $packageJson['devDependencies'] : []);

        $allComposer = array_fill_keys(array_merge($composerDeps, $composerDevDeps), true);
        $allPackage = array_fill_keys(array_merge($packageDeps, $packageDevDeps), true);

        if (isset($allComposer['laravel/framework'])) {
            $signals[] = 'Laravel';
        }
        if (isset($allComposer['inertiajs/inertia-laravel'])) {
            $signals[] = 'Inertia.js (Laravel adapter)';
        }
        if (isset($allPackage['@inertiajs/vue3'])) {
            $signals[] = 'Inertia.js + Vue 3';
        }
        if (isset($allPackage['vue'])) {
            $signals[] = 'Vue';
        }
        if (isset($allPackage['tailwindcss'])) {
            $signals[] = 'Tailwind CSS';
        }
        if (isset($allPackage['vite'])) {
            $signals[] = 'Vite';
        }
        if (isset($allComposer['laravel/horizon'])) {
            $signals[] = 'Laravel Horizon';
        }
        if (isset($allComposer['laravel/reverb'])) {
            $signals[] = 'Laravel Reverb';
        }
        if (isset($allComposer['laravel/sanctum'])) {
            $signals[] = 'Laravel Sanctum';
        }
        if (isset($allComposer['pestphp/pest']) || isset($allComposer['phpunit/phpunit'])) {
            $signals[] = 'PHP Testing Framework';
        }

        return $signals;
    }

    /**
     * @param  array<string, mixed>  $dependencyPayload
     * @param  array<string, mixed>  $routePayload
     * @param  array<string, mixed>  $frontendPayload
     * @return array<int, string>
     */
    private function inferredStackFromArtifacts(
        array $dependencyPayload,
        array $routePayload,
        array $frontendPayload,
    ): array {
        $signals = [];

        $ecosystems = $this->stringList($dependencyPayload['ecosystems'] ?? []);
        if (in_array('php', $ecosystems, true)) {
            $signals[] = 'PHP';
        }
        if (in_array('node', $ecosystems, true)) {
            $signals[] = 'Node.js Tooling';
        }
        if ((int) ($routePayload['route_file_count'] ?? 0) > 0) {
            $signals[] = 'Route-driven HTTP surface';
        }
        if ((int) ($frontendPayload['entrypoint_count'] ?? 0) > 0) {
            $signals[] = 'Frontend module tree';
        }

        return $signals;
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
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function sampleList(array $paths, int $limit): array
    {
        return array_slice($paths, 0, max(1, $limit));
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function basenameList(array $paths, int $limit): array
    {
        $basenames = [];
        foreach ($paths as $path) {
            $basenames[] = basename($path);
        }

        $basenames = array_values(array_unique($basenames));
        sort($basenames, SORT_STRING);

        return $this->sampleList($basenames, $limit);
    }

    /**
     * @param  mixed  $warnings
     * @return array<int, string>
     */
    private function warningCodes(mixed $warnings): array
    {
        if (! is_array($warnings)) {
            return [];
        }

        $codes = [];
        foreach ($warnings as $warning) {
            if (! is_array($warning)) {
                continue;
            }

            $code = $warning['code'] ?? null;
            if (! is_string($code) || trim($code) === '') {
                continue;
            }

            $codes[] = trim($code);
        }

        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * @param  mixed  $blockingFailures
     * @return array<int, string>
     */
    private function blockingFailureCodes(mixed $blockingFailures): array
    {
        if (! is_array($blockingFailures)) {
            return [];
        }

        $codes = [];
        foreach ($blockingFailures as $blockingFailure) {
            if (! is_array($blockingFailure)) {
                continue;
            }

            $code = $blockingFailure['code'] ?? null;
            if (! is_string($code) || trim($code) === '') {
                continue;
            }

            $codes[] = trim($code);
        }

        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function canonicalJson(array $value): string
    {
        $normalized = $this->normalize($value);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return mixed
     */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isList($value)) {
            return array_map(fn (mixed $entry): mixed => $this->normalize($entry), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) {
            $value[$key] = $this->normalize($entry);
        }

        return $value;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
