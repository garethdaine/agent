<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

use InvalidArgumentException;

class AnalyzerRegistry
{
    /**
     * @var array<string, AnalyzerInterface>
     */
    private array $analyzers;

    /**
     * @var array<string, array<int, string>>
     */
    private array $profiles;

    /**
     * @param  array<int, AnalyzerInterface>|null  $analyzers
     * @param  array<string, array<int, string>>|null  $profiles
     */
    public function __construct(?array $analyzers = null, ?array $profiles = null)
    {
        $analyzerList = $analyzers ?? [
            new FilesystemManifestAnalyzer,
            new DependencyManifestAnalyzer,
            new RoutingSurfaceAnalyzer,
            new DataModelSurfaceAnalyzer,
            new AsyncWorkflowsSurfaceAnalyzer,
            new FrontendSurfaceAnalyzer,
            new ArchitecturePatternsAnalyzer,
            new TestCoverageMapAnalyzer,
            new RiskHotspotAnalyzer,
            new CodeQualityStandardsAnalyzer,
        ];

        $this->analyzers = [];
        foreach ($analyzerList as $analyzer) {
            $this->analyzers[$analyzer->key()] = $analyzer;
        }

        $this->profiles = $profiles ?? [
            'default' => [
                'filesystem_manifest',
                'dependency_manifest',
                'routing_surface',
                'data_model_surface',
                'async_workflows_surface',
                'frontend_surface',
                'architecture_patterns',
                'test_coverage_map',
                'risk_hotspot',
                'code_quality_standards',
            ],
        ];
    }

    public function get(string $analyzerKey): AnalyzerInterface
    {
        $analyzer = $this->analyzers[$analyzerKey] ?? null;
        if ($analyzer === null) {
            throw new InvalidArgumentException(sprintf('Analyzer [%s] is not registered.', $analyzerKey));
        }

        return $analyzer;
    }

    /**
     * @return array{analyzers: array<int, AnalyzerInterface>, skipped: array<int, array<string, mixed>>}
     */
    public function forProfile(string $profile, array $snapshot): array
    {
        $profileAnalyzers = $this->profiles[$profile] ?? null;
        if (! is_array($profileAnalyzers)) {
            $skipped = [];
            foreach (array_keys($this->analyzers) as $key) {
                $skipped[] = [
                    'analyzer_key' => $key,
                    'reason' => 'unsupported_profile',
                    'profile' => $profile,
                ];
            }

            return [
                'analyzers' => [],
                'skipped' => $skipped,
            ];
        }

        $selected = [];
        $skipped = [];
        $seen = [];

        foreach ($profileAnalyzers as $key) {
            if (! is_string($key) || isset($seen[$key])) { // @phpstan-ignore function.alreadyNarrowedType
                continue;
            }
            $seen[$key] = true;

            $analyzer = $this->analyzers[$key] ?? null;
            if ($analyzer === null) {
                $skipped[] = [
                    'analyzer_key' => $key,
                    'reason' => 'analyzer_not_registered',
                ];

                continue;
            }

            if (! $analyzer->supports($snapshot)) {
                $skipped[] = [
                    'analyzer_key' => $key,
                    'reason' => 'unsupported_stack',
                ];

                continue;
            }

            $selected[] = $analyzer;
        }

        return [
            'analyzers' => $selected,
            'skipped' => $skipped,
        ];
    }
}
