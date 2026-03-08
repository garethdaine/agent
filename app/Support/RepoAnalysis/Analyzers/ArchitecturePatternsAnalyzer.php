<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class ArchitecturePatternsAnalyzer extends AbstractAnalyzer
{
    /**
     * @var array<int, string>
     */
    private const SOURCE_EXTENSIONS = [
        'php', 'js', 'jsx', 'ts', 'tsx', 'vue', 'svelte',
        'py', 'go', 'rs', 'rb', 'java', 'kt', 'kts', 'cs', 'swift', 'dart',
    ];

    /**
     * @var array<int, array{
     *   key: string,
     *   name: string,
     *   path_patterns: array<int, string>,
     *   content_patterns: array<int, string>
     * }>
     */
    private const PATTERN_RULES = [
        [
            'key' => 'repository_pattern',
            'name' => 'Repository Pattern',
            'path_patterns' => [
                '/(?:^|\/)repositories?(?:\/|$)/i',
                '/(?:^|[_.-])repository(?:[_.-]|$)/i',
            ],
            'content_patterns' => [
                '/\b(interface|class|struct|trait)\s+\w*Repository\b/i',
            ],
        ],
        [
            'key' => 'service_layer',
            'name' => 'Service Layer',
            'path_patterns' => [
                '/(?:^|\/)services?(?:\/|$)/i',
                '/(?:^|[_.-])service(?:[_.-]|$)/i',
            ],
            'content_patterns' => [
                '/\b(class|struct)\s+\w*Service\b/i',
            ],
        ],
        [
            'key' => 'factory_builder',
            'name' => 'Factory / Builder Pattern',
            'path_patterns' => [
                '/(?:^|[_.-])factory(?:[_.-]|$)/i',
                '/(?:^|[_.-])builder(?:[_.-]|$)/i',
            ],
            'content_patterns' => [
                '/\b(class|struct|interface|trait)\s+\w*(Factory|Builder)\b/i',
            ],
        ],
        [
            'key' => 'strategy_policy',
            'name' => 'Strategy / Policy Pattern',
            'path_patterns' => [
                '/(?:^|[_.-])strategy(?:[_.-]|$)/i',
                '/(?:^|[_.-])policy(?:[_.-]|$)/i',
            ],
            'content_patterns' => [
                '/\b(class|interface|struct|trait)\s+\w*(Strategy|Policy)\b/i',
            ],
        ],
        [
            'key' => 'event_observer_pubsub',
            'name' => 'Event / Observer / PubSub Pattern',
            'path_patterns' => [
                '/(?:^|\/)events?(?:\/|$)/i',
                '/(?:^|\/)listeners?(?:\/|$)/i',
                '/(?:^|\/)subscribers?(?:\/|$)/i',
                '/(?:^|\/)pubsub(?:\/|$)/i',
            ],
            'content_patterns' => [
                '/\b(event|listener|subscriber|publish|subscribe|emit)\b/i',
            ],
        ],
        [
            'key' => 'dependency_injection',
            'name' => 'Dependency Injection',
            'path_patterns' => [
                '/(?:^|\/)providers?(?:\/|$)/i',
                '/(?:^|\/)container(?:\/|$)/i',
                '/(?:^|\/)inject(?:ion)?(?:\/|$)/i',
            ],
            'content_patterns' => [
                '/\b__construct\s*\(/i',
                '/\bconstructor\s*\(/i',
                '/\b@Inject\b/i',
                '/\binject\s*\(/i',
                '/\bservice\s+container\b/i',
            ],
        ],
        [
            'key' => 'middleware_pipeline',
            'name' => 'Middleware / Pipeline Pattern',
            'path_patterns' => [
                '/(?:^|\/)middleware(?:\/|$)/i',
                '/(?:^|\/)pipeline(?:\/|$)/i',
                '/(?:^|\/)interceptors?(?:\/|$)/i',
            ],
            'content_patterns' => [
                '/\bmiddleware\b/i',
                '/\bpipeline\b/i',
                '/\binterceptor\b/i',
            ],
        ],
    ];

    public function key(): string
    {
        return 'architecture_patterns';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * @return array<int, string>
     */
    public function dependencies(): array
    {
        return ['filesystem_manifest'];
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $sourceFiles = $this->sourceFiles($paths);
        $warnings = [];

        if ($sourceFiles === []) {
            $warnings[] = [
                'code' => 'no_source_files_discovered',
                'message' => 'No source files were discovered for pattern analysis.',
            ];

            return $this->result([
                'snapshot_hash' => $this->snapshotHash($snapshot),
                'source_file_count' => 0,
                'detected_pattern_count' => 0,
                'detected_pattern_keys' => [],
                'detected_patterns' => [],
                'extension_distribution' => [],
                'architecture_signals' => [],
                'module_roots' => [],
            ], $warnings);
        }

        $detectedPatterns = [];
        foreach (self::PATTERN_RULES as $rule) {
            $detection = $this->detectPattern($snapshot, $sourceFiles, $rule);
            if ($detection === null) {
                continue;
            }

            $detectedPatterns[] = $detection;
        }

        $compositeSignals = $this->compositeArchitectureSignals($paths, $sourceFiles);
        $detectedPatterns = array_merge($detectedPatterns, $compositeSignals['patterns']);

        usort($detectedPatterns, static fn (array $left, array $right): int => strcmp(
            (string) ($left['pattern_key'] ?? ''),
            (string) ($right['pattern_key'] ?? '')
        ));

        if ($detectedPatterns === []) {
            $warnings[] = [
                'code' => 'no_patterns_detected',
                'message' => 'No strong architectural pattern signals were detected using deterministic heuristics.',
            ];
        }

        $detectedPatternKeys = array_values(array_unique(array_map(
            static fn (array $pattern): string => (string) ($pattern['pattern_key'] ?? ''),
            $detectedPatterns
        )));
        sort($detectedPatternKeys, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'source_file_count' => count($sourceFiles),
            'detected_pattern_count' => count($detectedPatterns),
            'detected_pattern_keys' => $detectedPatternKeys,
            'detected_patterns' => $detectedPatterns,
            'extension_distribution' => $this->extensionDistribution($sourceFiles),
            'architecture_signals' => $compositeSignals['signals'],
            'module_roots' => $this->moduleRoots($paths),
        ], $warnings);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function sourceFiles(array $paths): array
    {
        $sourceFiles = array_values(array_filter($paths, static function (string $path): bool {
            $normalized = strtolower(str_replace('\\', '/', $path));
            $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

            if (in_array($extension, self::SOURCE_EXTENSIONS, true)) {
                return true;
            }

            return false;
        }));

        sort($sourceFiles, SORT_STRING);

        return $sourceFiles;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, string>  $sourceFiles
     * @param  array{
     *   key: string,
     *   name: string,
     *   path_patterns: array<int, string>,
     *   content_patterns: array<int, string>
     * }  $rule
     * @return array<string, mixed>|null
     */
    private function detectPattern(array $snapshot, array $sourceFiles, array $rule): ?array
    {
        $evidenceFiles = [];
        $signalTypes = [];

        foreach ($sourceFiles as $path) {
            $normalizedPath = str_replace('\\', '/', $path);
            $basename = basename($normalizedPath);
            $matched = false;

            foreach ($rule['path_patterns'] as $pathPattern) {
                if (preg_match($pathPattern, $normalizedPath) === 1 || preg_match($pathPattern, $basename) === 1) {
                    $matched = true;
                    $signalTypes[] = 'path';
                    break;
                }
            }

            if (! $matched && $rule['content_patterns'] !== []) {
                $content = $this->fileContentByPath($snapshot, $path) ?? '';
                if ($content !== '') {
                    foreach ($rule['content_patterns'] as $contentPattern) {
                        if (preg_match($contentPattern, $content) === 1) {
                            $matched = true;
                            $signalTypes[] = 'content';
                            break;
                        }
                    }
                }
            }

            if ($matched) {
                $evidenceFiles[] = $normalizedPath;
            }
        }

        $evidenceFiles = array_values(array_unique($evidenceFiles));
        sort($evidenceFiles, SORT_STRING);

        if ($evidenceFiles === []) {
            return null;
        }

        $signalTypes = array_values(array_unique($signalTypes));
        sort($signalTypes, SORT_STRING);

        return [
            'pattern_key' => $rule['key'],
            'pattern_name' => $rule['name'],
            'confidence' => $this->confidenceLevel(count($evidenceFiles), $signalTypes),
            'signal_types' => $signalTypes,
            'evidence_file_count' => count($evidenceFiles),
            'evidence_files' => array_slice($evidenceFiles, 0, 40),
        ];
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $sourceFiles
     * @return array{patterns: array<int, array<string, mixed>>, signals: array<int, string>}
     */
    private function compositeArchitectureSignals(array $paths, array $sourceFiles): array
    {
        $normalized = array_map(static fn (string $path): string => strtolower(str_replace('\\', '/', $path)), $paths);

        $hasController = $this->containsPathToken($normalized, ['controllers/', 'controller']);
        $hasModel = $this->containsPathToken($normalized, ['models/', 'model', 'entities/', 'entity']);
        $hasView = $this->containsPathToken($normalized, ['views/', 'templates/', 'components/', '.blade.php', '.vue', '.tsx', '.jsx', '.svelte']);

        $hasService = $this->containsPathToken($normalized, ['services/', 'service']);
        $hasRepository = $this->containsPathToken($normalized, ['repositories/', 'repository']);
        $hasRoutes = $this->containsPathToken($normalized, ['routes/', 'router', 'routing']);

        $patterns = [];
        $signals = [];

        if ($hasController && $hasModel && $hasView) {
            $signals[] = 'Model-View-Controller style separation is present.';
            $patterns[] = [
                'pattern_key' => 'mvc_separation',
                'pattern_name' => 'MVC Separation',
                'confidence' => 'high',
                'signal_types' => ['structure'],
                'evidence_file_count' => count($sourceFiles),
                'evidence_files' => [],
            ];
        }

        $layerMarkers = 0;
        foreach ([$hasController, $hasService, $hasRepository, $hasModel, $hasRoutes] as $marker) {
            if ($marker) {
                $layerMarkers++;
            }
        }

        if ($layerMarkers >= 3) {
            $signals[] = 'Layered architecture markers detected across routing/domain/service boundaries.';
            $patterns[] = [
                'pattern_key' => 'layered_architecture',
                'pattern_name' => 'Layered Architecture',
                'confidence' => $layerMarkers >= 4 ? 'high' : 'medium',
                'signal_types' => ['structure'],
                'evidence_file_count' => count($sourceFiles),
                'evidence_files' => [],
            ];
        }

        $topSegments = [];
        foreach ($normalized as $path) {
            $segment = explode('/', $path)[0] ?? ''; // @phpstan-ignore nullCoalesce.offset
            if ($segment !== '') {
                $topSegments[$segment] = true;
            }
        }

        if (isset($topSegments['packages']) || isset($topSegments['apps']) || isset($topSegments['services'])) {
            $signals[] = 'Multi-module repository layout detected (packages/apps/services style roots).';
        }

        $signals = array_values(array_unique($signals));

        return [
            'patterns' => $patterns,
            'signals' => $signals,
        ];
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $tokens
     */
    private function containsPathToken(array $paths, array $tokens): bool
    {
        foreach ($paths as $path) {
            foreach ($tokens as $token) {
                if (str_contains($path, strtolower($token))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $sourceFiles
     * @return array<string, int>
     */
    private function extensionDistribution(array $sourceFiles): array
    {
        $distribution = [];

        foreach ($sourceFiles as $path) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($extension === '') {
                continue;
            }

            $distribution[$extension] = ($distribution[$extension] ?? 0) + 1;
        }

        arsort($distribution, SORT_NUMERIC);

        return $distribution;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{name: string, file_count: int}>
     */
    private function moduleRoots(array $paths): array
    {
        $counts = [];
        foreach ($paths as $path) {
            $segment = explode('/', str_replace('\\', '/', $path))[0] ?? '(root)'; // @phpstan-ignore nullCoalesce.offset
            if ($segment === '') {
                $segment = '(root)';
            }

            $counts[$segment] = ($counts[$segment] ?? 0) + 1;
        }

        arsort($counts, SORT_NUMERIC);

        $roots = [];
        foreach (array_slice($counts, 0, 20, true) as $name => $fileCount) {
            $roots[] = [
                'name' => $name,
                'file_count' => $fileCount,
            ];
        }

        return $roots;
    }

    /**
     * @param  array<int, string>  $signalTypes
     */
    private function confidenceLevel(int $evidenceCount, array $signalTypes): string
    {
        $hasPathSignal = in_array('path', $signalTypes, true);
        $hasContentSignal = in_array('content', $signalTypes, true);

        if ($evidenceCount >= 8 || ($evidenceCount >= 3 && $hasPathSignal && $hasContentSignal)) {
            return 'high';
        }

        if ($evidenceCount >= 3) {
            return 'medium';
        }

        return 'low';
    }
}
