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
            $normalized = strtolower(str_replace('\\', '/', $path));

            if (str_starts_with($normalized, 'app/')
                || str_starts_with($normalized, 'src/')
                || str_starts_with($normalized, 'lib/')
                || str_starts_with($normalized, 'server/')
                || str_starts_with($normalized, 'backend/')
                || str_starts_with($normalized, 'api/')
                || str_starts_with($normalized, 'services/')
                || str_starts_with($normalized, 'database/')
                || str_starts_with($normalized, 'db/')
                || str_starts_with($normalized, 'routes/')
                || str_starts_with($normalized, 'config/')
                || str_starts_with($normalized, 'migrations/')) {
                return true;
            }

            return preg_match('/(?:^|\/)(schema|migration|route|router|controller|service|handler|worker|consumer|repository)[^\/]*\.(php|js|jsx|ts|tsx|py|go|rs|rb|java|kt|cs)$/i', $normalized) === 1;
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
