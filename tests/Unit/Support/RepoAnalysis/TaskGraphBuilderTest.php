<?php

declare(strict_types=1);

namespace Tests\Unit\Support\RepoAnalysis;

use App\Support\RepoAnalysis\Analyzers\AnalyzerRegistry;
use App\Support\RepoAnalysis\TaskGraphBuilder;
use Tests\TestCase;

class TaskGraphBuilderTest extends TestCase
{
    public function test_build_is_deterministic_for_same_profile_and_snapshot(): void
    {
        $registry = new AnalyzerRegistry;
        $builder = new TaskGraphBuilder($registry);
        $snapshot = $this->mixedStackSnapshot('snapshot-hash-001');

        $first = $builder->build($snapshot, 'default');
        $second = $builder->build($snapshot, 'default');

        $this->assertSame($first, $second);
        $this->assertSame(
            [
                'filesystem_manifest',
                'dependency_manifest',
                'routing_surface',
                'data_model_surface',
                'async_workflows_surface',
                'frontend_surface',
                'test_coverage_map',
                'risk_hotspot',
            ],
            array_map(static fn (array $task): string => $task['analyzer_key'], $first['tasks'])
        );
    }

    public function test_task_key_is_stable_for_same_inputs_and_changes_with_snapshot_hash(): void
    {
        $registry = new AnalyzerRegistry;
        $builder = new TaskGraphBuilder($registry);
        $snapshotA = $this->mixedStackSnapshot('snapshot-hash-a');
        $snapshotB = $this->mixedStackSnapshot('snapshot-hash-b');

        $first = $builder->build($snapshotA, 'default');
        $second = $builder->build($snapshotA, 'default');
        $third = $builder->build($snapshotB, 'default');

        $this->assertSame(
            array_column($first['tasks'], 'task_key'),
            array_column($second['tasks'], 'task_key')
        );
        $this->assertNotSame(
            array_column($first['tasks'], 'task_key'),
            array_column($third['tasks'], 'task_key')
        );
    }

    public function test_dependencies_match_analyzer_contract(): void
    {
        $registry = new AnalyzerRegistry;
        $builder = new TaskGraphBuilder($registry);
        $tasks = $builder->build($this->mixedStackSnapshot('snapshot-hash-002'), 'default')['tasks'];

        $tasksByAnalyzer = collect($tasks)->keyBy('analyzer_key');

        $this->assertSame([], $tasksByAnalyzer['filesystem_manifest']['depends_on']);
        $this->assertSame(['filesystem_manifest'], $tasksByAnalyzer['dependency_manifest']['depends_on']);
        $this->assertSame(['dependency_manifest'], $tasksByAnalyzer['routing_surface']['depends_on']);
        $this->assertSame(['dependency_manifest'], $tasksByAnalyzer['data_model_surface']['depends_on']);
        $this->assertSame(['data_model_surface'], $tasksByAnalyzer['async_workflows_surface']['depends_on']);
        $this->assertSame(['dependency_manifest'], $tasksByAnalyzer['frontend_surface']['depends_on']);
        $this->assertSame(['dependency_manifest'], $tasksByAnalyzer['test_coverage_map']['depends_on']);
        $this->assertSame(
            ['filesystem_manifest', 'dependency_manifest', 'test_coverage_map'],
            $tasksByAnalyzer['risk_hotspot']['depends_on']
        );
    }

    public function test_unsupported_profile_returns_no_tasks_and_explicit_skip_reason(): void
    {
        $registry = new AnalyzerRegistry;
        $builder = new TaskGraphBuilder($registry);

        $plan = $builder->build($this->mixedStackSnapshot('snapshot-hash-003'), 'does-not-exist');

        $this->assertSame([], $plan['tasks']);
        $this->assertNotEmpty($plan['skipped']);
        $this->assertTrue(collect($plan['skipped'])->contains(
            static fn (array $entry): bool => ($entry['reason'] ?? null) === 'unsupported_profile'
        ));
    }

    /**
     * @return array{
     *   snapshot_hash: string,
     *   manifest: array{
     *     files: array<int, array{path: string}>
     *   }
     * }
     */
    private function mixedStackSnapshot(string $snapshotHash): array
    {
        return [
            'snapshot_hash' => $snapshotHash,
            'manifest' => [
                'files' => [
                    ['path' => 'app/Events/UserRegistered.php'],
                    ['path' => 'app/Jobs/SendDigestJob.php'],
                    ['path' => 'app/Models/User.php'],
                    ['path' => 'artisan'],
                    ['path' => 'composer.json'],
                    ['path' => 'database/migrations/2026_01_01_000000_create_users_table.php'],
                    ['path' => 'package-lock.json'],
                    ['path' => 'package.json'],
                    ['path' => 'resources/js/app.js'],
                    ['path' => 'routes/web.php'],
                    ['path' => 'tests/Feature/SmokeTest.php'],
                ],
            ],
        ];
    }
}
