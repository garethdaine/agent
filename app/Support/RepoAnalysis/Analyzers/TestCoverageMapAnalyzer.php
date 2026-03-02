<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class TestCoverageMapAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'test_coverage_map';
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
        $testFiles = array_values(array_filter($paths, static function (string $path): bool {
            if (str_starts_with($path, 'tests/')) {
                return true;
            }

            return str_contains($path, '.test.') || str_contains($path, '.spec.');
        }));
        sort($testFiles, SORT_STRING);

        $warnings = [];
        $warningArtifactPath = null;

        if ($testFiles === []) {
            $warnings[] = [
                'code' => 'empty_test_suite',
                'message' => 'No tests discovered for coverage mapping.',
            ];
            $warningArtifactPath = 'artifacts/warnings/no-tests-warning.json';
        }

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'test_files' => $testFiles,
            'test_file_count' => count($testFiles),
        ], $warnings, $warningArtifactPath);
    }
}
