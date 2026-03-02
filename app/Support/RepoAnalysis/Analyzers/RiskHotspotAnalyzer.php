<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class RiskHotspotAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'risk_hotspot';
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
        return ['filesystem_manifest', 'dependency_manifest', 'test_coverage_map'];
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $hotspots = array_values(array_filter($paths, static function (string $path): bool {
            return str_starts_with($path, 'app/')
                || str_starts_with($path, 'database/')
                || str_starts_with($path, 'routes/')
                || str_starts_with($path, 'config/');
        }));
        sort($hotspots, SORT_STRING);

        $warnings = [];
        $hasTests = collect($paths)->contains(
            static fn (string $path): bool => str_starts_with($path, 'tests/')
                || str_contains($path, '.test.')
                || str_contains($path, '.spec.')
        );

        if (! $hasTests && $hotspots !== []) {
            $warnings[] = [
                'code' => 'risk_without_tests',
                'message' => 'Risk hotspots detected without test coverage files.',
            ];
        }

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'hotspot_files' => $hotspots,
            'hotspot_count' => count($hotspots),
        ], $warnings);
    }
}
