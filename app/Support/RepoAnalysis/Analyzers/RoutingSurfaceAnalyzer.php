<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class RoutingSurfaceAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'routing_surface';
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
        return ['dependency_manifest'];
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $routeFiles = array_values(array_filter($paths, static function (string $path): bool {
            $normalized = strtolower(str_replace('\\', '/', $path));
            $basename = basename($normalized);

            if (str_starts_with($normalized, 'routes/')
                || str_contains($normalized, '/routes/')
                || str_contains($normalized, '/router/')
                || str_contains($normalized, '/routing/')
                || str_starts_with($normalized, 'app/api/')
                || str_contains($normalized, '/api/routes/')
                || str_contains($normalized, '/endpoints/')) {
                return true;
            }

            return preg_match('/(?:^|[._-])(route|router|routing|endpoint|api)(?:[._-]|$)/i', $basename) === 1;
        }));
        sort($routeFiles, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'route_files' => $routeFiles,
            'route_file_count' => count($routeFiles),
        ]);
    }
}
