<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class LaravelRoutesAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'laravel_routes';
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
        $paths = $this->snapshotPaths($snapshot);

        foreach ($paths as $path) {
            if ($path === 'artisan' || str_starts_with($path, 'routes/')) {
                return true;
            }
        }

        return false;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $routeFiles = array_values(array_filter($paths, static fn (string $path): bool => str_starts_with($path, 'routes/')));
        sort($routeFiles, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'route_files' => $routeFiles,
            'route_file_count' => count($routeFiles),
        ]);
    }
}
