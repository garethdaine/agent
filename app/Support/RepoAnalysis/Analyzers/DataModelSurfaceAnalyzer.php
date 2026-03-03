<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class DataModelSurfaceAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'data_model_surface';
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
        $modelFiles = array_values(array_filter(
            $paths,
            static function (string $path): bool {
                $normalized = strtolower(str_replace('\\', '/', $path));
                $basename = basename($normalized);

                if (str_contains($normalized, '/models/')
                    || str_contains($normalized, '/entities/')
                    || str_contains($normalized, '/domain/')
                    || str_starts_with($normalized, 'models/')
                    || str_starts_with($normalized, 'entities/')) {
                    return true;
                }

                return preg_match('/(?:^|[._-])(model|entity|aggregate|schema|orm)(?:[._-]|$)/i', $basename) === 1;
            }
        ));
        sort($modelFiles, SORT_STRING);

        $migrationFiles = array_values(array_filter(
            $paths,
            static function (string $path): bool {
                $normalized = strtolower(str_replace('\\', '/', $path));

                if (str_starts_with($normalized, 'database/migrations/')
                    || str_contains($normalized, '/migrations/')
                    || str_contains($normalized, '/db/migrate/')
                    || str_contains($normalized, '/alembic/')
                    || str_contains($normalized, '/flyway/')
                    || str_contains($normalized, '/prisma/migrations/')) {
                    return true;
                }

                return preg_match('/migration/i', basename($normalized)) === 1;
            }
        ));
        sort($migrationFiles, SORT_STRING);

        $schemaFiles = array_values(array_filter(
            $paths,
            static function (string $path): bool {
                $normalized = strtolower(str_replace('\\', '/', $path));
                $basename = basename($normalized);

                if (in_array($basename, ['schema.prisma', 'schema.sql', 'dbschema.sql'], true)) {
                    return true;
                }

                return preg_match('/(?:schema|erd|database|ddl)\.(sql|prisma|json|yml|yaml)$/i', $basename) === 1;
            }
        ));
        sort($schemaFiles, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'model_files' => $modelFiles,
            'migration_files' => $migrationFiles,
            'schema_files' => $schemaFiles,
            'model_count' => count($modelFiles),
            'migration_count' => count($migrationFiles),
            'schema_count' => count($schemaFiles),
        ]);
    }
}
