<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class FrontendModuleGraphAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'frontend_module_graph';
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
            if ($path === 'package.json'
                || str_starts_with($path, 'resources/js/')
                || str_starts_with($path, 'resources/ts/')
                || str_starts_with($path, 'src/')) {
                return true;
            }
        }

        return false;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $entrypointCandidates = array_values(array_filter($paths, static function (string $path): bool {
            return str_starts_with($path, 'resources/js/')
                || str_starts_with($path, 'resources/ts/')
                || str_starts_with($path, 'src/');
        }));
        sort($entrypointCandidates, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'entrypoints' => $entrypointCandidates,
            'entrypoint_count' => count($entrypointCandidates),
            'has_package_manifest' => in_array('package.json', $paths, true),
        ]);
    }
}
