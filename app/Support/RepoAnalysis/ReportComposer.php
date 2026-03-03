<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Support\Agent\FeatureFlagManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ReportComposer
{
    public function __construct(
        private readonly FeatureFlagManager $featureFlags,
    ) {}

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
            ->map(function ($artifact): array {
                $rawPayload = is_array($artifact->payload_json) ? $artifact->payload_json : [];
                $normalizedPayload = is_array($rawPayload['payload'] ?? null)
                    ? $rawPayload['payload']
                    : $rawPayload;

                return [
                    'artifact_key' => (string) $artifact->artifact_key,
                    'artifact_type' => (string) $artifact->artifact_type,
                    'content_hash' => (string) $artifact->content_hash,
                    'payload_json' => is_array($normalizedPayload) ? $normalizedPayload : [],
                    'metadata_json' => is_array($artifact->metadata_json) ? $artifact->metadata_json : [],
                ];
            })
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
        $aiSections = $this->extractAiSections($artifactRecords);
        $fullReportMarkdown = $this->composeFullReportMarkdown($aiSections);

        $payload = [
            'session_id' => $session->id,
            'snapshot_hash' => (string) $session->snapshot_hash,
            'runner_type' => (string) ($session->runner_type ?: 'claude'),
            'artifact_count' => count($artifacts),
            'artifacts' => $artifacts,
            'coverage' => $coverageSummary,
            'ai_report' => [
                'enabled' => $this->featureFlags->enabled(FeatureFlagManager::REPO_ANALYSIS_AI_ENABLED),
                'section_count' => count($aiSections),
                'has_final_report_section' => $this->hasFinalReportSection($aiSections),
                'sections' => $aiSections,
            ],
            'full_report_markdown' => $fullReportMarkdown,
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
                'ai_section_count' => count($aiSections),
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

        $dependencyPayload = $this->artifactPayload($artifactByType, ['dependency_manifest']);
        $routePayload = $this->artifactPayload($artifactByType, ['routing_surface', 'laravel_routes']);
        $modelPayload = $this->artifactPayload($artifactByType, ['data_model_surface', 'laravel_models_migrations']);
        $queuePayload = $this->artifactPayload($artifactByType, ['async_workflows_surface', 'queue_jobs_events']);
        $frontendPayload = $this->artifactPayload($artifactByType, ['frontend_surface', 'frontend_module_graph']);
        $testPayload = $this->artifactPayload($artifactByType, ['test_coverage_map']);
        $riskPayload = $this->artifactPayload($artifactByType, ['risk_hotspot']);

        $languageBreakdown = $this->languageBreakdown($paths);
        $topDirectories = $this->topLevelDistribution($paths);
        $dependencySummary = $this->dependencySummary($composerJson, $packageJson, $dependencyPayload);

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
                'schemas' => $this->basenameList($this->stringList($modelPayload['schema_files'] ?? []), 30),
                'schema_count' => (int) ($modelPayload['schema_count'] ?? 0),
            ],
            'frontend' => [
                'entrypoints' => $this->sampleList($this->stringList($frontendPayload['entrypoints'] ?? []), 40),
                'entrypoint_count' => (int) ($frontendPayload['entrypoint_count'] ?? 0),
                'has_package_manifest' => (bool) ($frontendPayload['has_package_manifest'] ?? false),
                'frontend_markers' => $this->sampleList($this->stringList($frontendPayload['frontend_markers'] ?? []), 20),
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
                'Analysis combines deterministic repository scanning with AI synthesis and does not execute full runtime behavior.',
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
     * @param  array<string, array{
     *   artifact_key: string,
     *   artifact_type: string,
     *   content_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>
     * }>  $artifactByType
     * @param  array<int, string>  $types
     * @return array<string, mixed>
     */
    private function artifactPayload(array $artifactByType, array $types): array
    {
        foreach ($types as $type) {
            if (! is_string($type) || $type === '') {
                continue;
            }

            $payload = data_get($artifactByType, $type.'.payload_json');
            if (is_array($payload)) {
                return $payload;
            }
        }

        return [];
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
            'svelte' => 'Svelte',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'sass' => 'Sass',
            'json' => 'JSON',
            'yml' => 'YAML',
            'yaml' => 'YAML',
            'md' => 'Markdown',
            'markdown' => 'Markdown',
            'blade.php' => 'Blade',
            'py' => 'Python',
            'go' => 'Go',
            'rs' => 'Rust',
            'rb' => 'Ruby',
            'java' => 'Java',
            'kt' => 'Kotlin',
            'kts' => 'Kotlin',
            'cs' => 'C#',
            'swift' => 'Swift',
            'dart' => 'Dart',
            'cpp' => 'C++',
            'cc' => 'C++',
            'cxx' => 'C++',
            'c' => 'C',
            'h' => 'C/C++ Header',
            'hpp' => 'C/C++ Header',
            'hh' => 'C/C++ Header',
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
     * @param  array<string, mixed>  $dependencyPayload
     * @return array<string, mixed>
     */
    private function dependencySummary(?array $composerJson, ?array $packageJson, array $dependencyPayload): array
    {
        $phpRuntime = $this->dependencyPairs($composerJson['require'] ?? []);
        $phpDev = $this->dependencyPairs($composerJson['require-dev'] ?? []);
        $nodeRuntime = $this->dependencyPairs($packageJson['dependencies'] ?? []);
        $nodeDev = $this->dependencyPairs($packageJson['devDependencies'] ?? []);
        $ecosystems = $this->stringList($dependencyPayload['ecosystems'] ?? []);
        $manifests = $this->stringList($dependencyPayload['manifests'] ?? []);
        $lockfiles = $this->stringList($dependencyPayload['lockfiles'] ?? []);

        return [
            'ecosystems' => $ecosystems,
            'manifest_count' => count($manifests),
            'manifests' => array_slice($manifests, 0, 80),
            'lockfile_count' => count($lockfiles),
            'lockfiles' => array_slice($lockfiles, 0, 80),
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

        if ($allComposer !== []) {
            $signals[] = 'PHP package ecosystem';
        }

        if ($allPackage !== []) {
            $signals[] = 'Node package ecosystem';
        }

        if (isset($allComposer['laravel/framework'])) {
            $signals[] = 'Laravel';
        }
        if (isset($allComposer['symfony/framework-bundle'])) {
            $signals[] = 'Symfony';
        }
        if (isset($allComposer['slim/slim'])) {
            $signals[] = 'Slim Framework';
        }
        if (isset($allComposer['yiisoft/yii2'])) {
            $signals[] = 'Yii 2';
        }
        if (isset($allComposer['codeigniter4/framework'])) {
            $signals[] = 'CodeIgniter 4';
        }
        if (isset($allComposer['inertiajs/inertia-laravel'])) {
            $signals[] = 'Inertia.js (Laravel adapter)';
        }
        if (isset($allComposer['livewire/livewire'])) {
            $signals[] = 'Laravel Livewire';
        }
        if (isset($allComposer['laravel/octane'])) {
            $signals[] = 'Laravel Octane';
        }
        if (isset($allPackage['@inertiajs/vue3'])) {
            $signals[] = 'Inertia.js + Vue 3';
        }
        if (isset($allPackage['react'])) {
            $signals[] = 'React';
        }
        if (isset($allPackage['next'])) {
            $signals[] = 'Next.js';
        }
        if (isset($allPackage['vue']) || isset($allPackage['@vue/runtime-core'])) {
            $signals[] = 'Vue';
        }
        if (isset($allPackage['nuxt'])) {
            $signals[] = 'Nuxt';
        }
        if (isset($allPackage['svelte'])) {
            $signals[] = 'Svelte';
        }
        if (isset($allPackage['@angular/core'])) {
            $signals[] = 'Angular';
        }
        if (isset($allPackage['nestjs/core'])) {
            $signals[] = 'NestJS';
        }
        if (isset($allPackage['express'])) {
            $signals[] = 'Express';
        }
        if (isset($allPackage['fastify'])) {
            $signals[] = 'Fastify';
        }
        if (isset($allPackage['koa'])) {
            $signals[] = 'Koa';
        }
        if (isset($allPackage['astro'])) {
            $signals[] = 'Astro';
        }
        if (isset($allPackage['tailwindcss'])) {
            $signals[] = 'Tailwind CSS';
        }
        if (isset($allPackage['vite'])) {
            $signals[] = 'Vite';
        }
        if (isset($allPackage['webpack'])) {
            $signals[] = 'Webpack';
        }
        if (isset($allPackage['typescript'])) {
            $signals[] = 'TypeScript';
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
        if (isset($allPackage['vitest']) || isset($allPackage['jest']) || isset($allPackage['mocha'])) {
            $signals[] = 'JavaScript Testing Framework';
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
        if (in_array('javascript', $ecosystems, true)) {
            $signals[] = 'JavaScript/TypeScript Tooling';
        }
        if (in_array('python', $ecosystems, true)) {
            $signals[] = 'Python Tooling';
        }
        if (in_array('go', $ecosystems, true)) {
            $signals[] = 'Go Tooling';
        }
        if (in_array('rust', $ecosystems, true)) {
            $signals[] = 'Rust Tooling';
        }
        if (in_array('ruby', $ecosystems, true)) {
            $signals[] = 'Ruby Tooling';
        }
        if (in_array('jvm', $ecosystems, true)) {
            $signals[] = 'JVM Tooling';
        }
        if (in_array('dotnet', $ecosystems, true)) {
            $signals[] = '.NET Tooling';
        }
        if (in_array('deno', $ecosystems, true)) {
            $signals[] = 'Deno Tooling';
        }
        if (in_array('elixir', $ecosystems, true)) {
            $signals[] = 'Elixir Tooling';
        }
        if (in_array('swift', $ecosystems, true)) {
            $signals[] = 'Swift Tooling';
        }
        if (in_array('dart_flutter', $ecosystems, true)) {
            $signals[] = 'Dart/Flutter Tooling';
        }
        if (in_array('cpp', $ecosystems, true)) {
            $signals[] = 'C/C++ Tooling';
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
     * @param  array<int, array{
     *   artifact_key: string,
     *   artifact_type: string,
     *   content_hash: string,
     *   payload_json: array<string, mixed>,
     *   metadata_json: array<string, mixed>
     * }>  $artifactRecords
     * @return array<int, array<string, mixed>>
     */
    private function extractAiSections(array $artifactRecords): array
    {
        $sections = [];
        $order = [
            'overview' => 10,
            'backend' => 20,
            'frontend' => 30,
            'quality_risk' => 40,
            'final_report' => 90,
        ];

        foreach ($artifactRecords as $artifactRecord) {
            if (($artifactRecord['artifact_type'] ?? '') !== 'ai_analysis_section') {
                continue;
            }

            $payload = is_array($artifactRecord['payload_json'] ?? null)
                ? $artifactRecord['payload_json']
                : [];

            $sectionKey = trim((string) ($payload['section_key'] ?? ''));
            $sectionTitle = trim((string) ($payload['section_title'] ?? ''));
            $summaryMarkdown = trim((string) ($payload['summary_markdown'] ?? ''));

            if ($sectionKey === '') {
                $sectionKey = trim((string) ($payload['task_name'] ?? ''));
            }
            if ($sectionTitle === '') {
                $sectionTitle = Str::title(str_replace('_', ' ', $sectionKey));
            }

            $sections[] = [
                'section_key' => $sectionKey,
                'section_title' => $sectionTitle,
                'task_key' => (string) ($payload['task_key'] ?? ''),
                'task_name' => (string) ($payload['task_name'] ?? ''),
                'summary_markdown' => $summaryMarkdown,
                'goals' => $this->stringList($payload['goals'] ?? []),
                'constraints' => $this->stringList($payload['constraints'] ?? []),
                'acceptance_criteria' => $this->stringList($payload['acceptance_criteria'] ?? []),
                'open_questions' => $this->stringList($payload['open_questions'] ?? []),
                '_sort' => $order[$sectionKey] ?? 50,
            ];
        }

        usort($sections, static function (array $left, array $right): int {
            $leftOrder = (int) ($left['_sort'] ?? 50);
            $rightOrder = (int) ($right['_sort'] ?? 50);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['section_key'] ?? ''), (string) ($right['section_key'] ?? ''));
        });

        return array_map(static function (array $section): array {
            unset($section['_sort']);

            return $section;
        }, $sections);
    }

    /**
     * @param  array<int, array<string, mixed>>  $aiSections
     */
    private function hasFinalReportSection(array $aiSections): bool
    {
        foreach ($aiSections as $section) {
            if ((string) ($section['section_key'] ?? '') === 'final_report'
                && trim((string) ($section['summary_markdown'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $aiSections
     */
    private function composeFullReportMarkdown(array $aiSections): string
    {
        foreach ($aiSections as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            $sectionMarkdown = trim((string) ($section['summary_markdown'] ?? ''));

            if ($sectionKey === 'final_report' && $sectionMarkdown !== '') {
                return $sectionMarkdown;
            }
        }

        $blocks = [];

        foreach ($aiSections as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            if ($sectionKey === 'final_report') {
                continue;
            }

            $sectionMarkdown = trim((string) ($section['summary_markdown'] ?? ''));
            if ($sectionMarkdown === '') {
                continue;
            }

            $sectionTitle = trim((string) ($section['section_title'] ?? ''));
            if ($sectionTitle !== '') {
                $blocks[] = '## '.$sectionTitle;
            }
            $blocks[] = $sectionMarkdown;
        }

        $composed = trim(implode("\n\n", $blocks));
        if ($composed === '') {
            return '';
        }

        return preg_replace("/\n{3,}/", "\n\n", $composed) ?? $composed;
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
