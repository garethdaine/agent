<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class LaravelModelsMigrationsAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'laravel_models_migrations';
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
            if (str_starts_with($path, 'app/Models/') || str_starts_with($path, 'database/migrations/')) {
                return true;
            }
        }

        return false;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $modelFiles = array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_starts_with($path, 'app/Models/')
        ));
        sort($modelFiles, SORT_STRING);

        $migrationFiles = array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_starts_with($path, 'database/migrations/')
        ));
        sort($migrationFiles, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'model_files' => $modelFiles,
            'migration_files' => $migrationFiles,
            'model_count' => count($modelFiles),
            'migration_count' => count($migrationFiles),
        ]);
    }
}
