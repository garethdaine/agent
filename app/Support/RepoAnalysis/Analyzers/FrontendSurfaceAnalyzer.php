<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class FrontendSurfaceAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'frontend_surface';
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
        $entrypointCandidates = array_values(array_filter($paths, static function (string $path): bool {
            $normalized = strtolower(str_replace('\\', '/', $path));

            if (str_starts_with($normalized, 'resources/js/')
                || str_starts_with($normalized, 'resources/ts/')
                || str_starts_with($normalized, 'src/')
                || str_starts_with($normalized, 'frontend/')
                || str_starts_with($normalized, 'client/')
                || str_starts_with($normalized, 'web/')
                || str_contains($normalized, '/frontend/')
                || str_contains($normalized, '/client/')
                || str_contains($normalized, '/ui/')) {
                return preg_match('/\.(js|jsx|ts|tsx|vue|svelte)$/i', $normalized) === 1;
            }

            return false;
        }));
        sort($entrypointCandidates, SORT_STRING);

        $frontendMarkers = array_values(array_filter($paths, static function (string $path): bool {
            $normalized = strtolower(str_replace('\\', '/', $path));
            $basename = basename($normalized);

            return in_array($basename, ['package.json', 'vite.config.js', 'vite.config.ts', 'webpack.config.js', 'next.config.js', 'nuxt.config.ts'], true);
        }));
        sort($frontendMarkers, SORT_STRING);

        $hasPackageManifest = collect($paths)->contains(
            static fn (string $path): bool => basename(strtolower(str_replace('\\', '/', $path))) === 'package.json'
        );

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'entrypoints' => $entrypointCandidates,
            'entrypoint_count' => count($entrypointCandidates),
            'has_package_manifest' => $hasPackageManifest,
            'frontend_markers' => $frontendMarkers,
        ]);
    }
}
