<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis\Analyzers;

use App\Support\RepoAnalysis\Analyzers\AnalyzerRegistry;
use Tests\TestCase;

class AnalyzerContractsTest extends TestCase
{
    public function test_normalized_output_hash_is_stable_for_equivalent_input_ordering(): void
    {
        $registry = new AnalyzerRegistry;
        $analyzer = $registry->get('filesystem_manifest');

        $unorderedSnapshot = [
            'snapshot_hash' => 'snapshot-001',
            'manifest' => [
                'files' => [
                    ['path' => 'routes/web.php'],
                    ['path' => 'app/Models/User.php'],
                    ['path' => 'package.json'],
                ],
            ],
        ];

        $differentOrderSnapshot = [
            'snapshot_hash' => 'snapshot-001',
            'manifest' => [
                'files' => [
                    ['path' => 'package.json'],
                    ['path' => 'routes/web.php'],
                    ['path' => 'app/Models/User.php'],
                ],
            ],
        ];

        $first = $analyzer->analyze($unorderedSnapshot);
        $second = $analyzer->analyze($differentOrderSnapshot);

        $this->assertSame($first['output_hash'], $second['output_hash']);
    }

    public function test_dependency_manifest_analyzer_handles_missing_lockfiles_and_parser_errors(): void
    {
        $registry = new AnalyzerRegistry;
        $analyzer = $registry->get('dependency_manifest');

        $missingManifests = $analyzer->analyze([
            'snapshot_hash' => 'snapshot-002',
            'manifest' => [
                'files' => [
                    ['path' => 'README.md'],
                ],
            ],
        ]);

        $this->assertTrue(collect($missingManifests['warnings'])->contains(
            static fn (array $warning): bool => ($warning['code'] ?? null) === 'missing_manifests'
        ));

        $parserError = $analyzer->analyze([
            'snapshot_hash' => 'snapshot-003',
            'manifest' => [
                'files' => [
                    ['path' => 'package.json', 'content' => '{invalid-json'],
                    ['path' => 'composer.json', 'content' => '{"name":"demo/repo"}'],
                ],
            ],
        ]);

        $this->assertTrue(collect($parserError['warnings'])->contains(
            static fn (array $warning): bool => ($warning['code'] ?? null) === 'parser_error'
        ));
    }

    public function test_test_coverage_map_analyzer_flags_empty_test_suite_with_warning_artifact_path(): void
    {
        $registry = new AnalyzerRegistry;
        $analyzer = $registry->get('test_coverage_map');

        $result = $analyzer->analyze([
            'snapshot_hash' => 'snapshot-004',
            'manifest' => [
                'files' => [
                    ['path' => 'app/Models/User.php'],
                    ['path' => 'resources/js/app.js'],
                ],
            ],
        ]);

        $this->assertTrue(collect($result['warnings'])->contains(
            static fn (array $warning): bool => ($warning['code'] ?? null) === 'empty_test_suite'
        ));
        $this->assertSame('artifacts/warnings/no-tests-warning.json', $result['warning_artifact_path']);
    }

    public function test_registry_supports_mixed_stack_repositories_with_expected_analyzers(): void
    {
        $registry = new AnalyzerRegistry;
        $snapshot = [
            'snapshot_hash' => 'snapshot-005',
            'manifest' => [
                'files' => [
                    ['path' => 'artisan'],
                    ['path' => 'routes/web.php'],
                    ['path' => 'app/Models/User.php'],
                    ['path' => 'database/migrations/2026_01_01_000000_create_users_table.php'],
                    ['path' => 'resources/js/app.js'],
                    ['path' => 'package.json'],
                ],
            ],
        ];

        $selection = $registry->forProfile('default', $snapshot);
        $selectedKeys = array_map(static fn ($analyzer): string => $analyzer->key(), $selection['analyzers']);

        $this->assertContains('laravel_routes', $selectedKeys);
        $this->assertContains('laravel_models_migrations', $selectedKeys);
        $this->assertContains('frontend_module_graph', $selectedKeys);
    }
}
