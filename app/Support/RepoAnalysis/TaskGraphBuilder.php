<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Support\RepoAnalysis\Analyzers\AnalyzerInterface;
use App\Support\RepoAnalysis\Analyzers\AnalyzerRegistry;

class TaskGraphBuilder
{
    public function __construct(
        private readonly AnalyzerRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *   profile: string,
     *   snapshot_hash: string,
     *   tasks: array<int, array<string, mixed>>,
     *   skipped: array<int, array<string, mixed>>
     * }
     */
    public function build(array $snapshot, string $profile = 'default'): array
    {
        $selection = $this->registry->forProfile($profile, $snapshot);
        $selectedAnalyzers = $selection['analyzers'];
        $skipped = $selection['skipped'];
        $selectedAnalyzerKeys = array_fill_keys(
            array_map(static fn (AnalyzerInterface $analyzer): string => $analyzer->key(), $selectedAnalyzers),
            true
        );

        $snapshotHash = $this->snapshotHash($snapshot);
        $tasks = [];
        $sequence = 0;

        foreach ($selectedAnalyzers as $analyzer) {
            $dependencies = [];
            foreach ($analyzer->dependencies() as $dependency) {
                if (! is_string($dependency) || $dependency === '') { // @phpstan-ignore function.alreadyNarrowedType
                    continue;
                }

                if (! in_array($dependency, $dependencies, true)) {
                    $dependencies[] = $dependency;
                }
            }

            $missingDependency = collect($dependencies)->first(
                static fn (string $dependency): bool => ! isset($selectedAnalyzerKeys[$dependency])
            );

            if (is_string($missingDependency)) {
                $skipped[] = [
                    'analyzer_key' => $analyzer->key(),
                    'reason' => 'missing_dependency',
                    'dependency' => $missingDependency,
                ];

                continue;
            }

            $sequence++;

            $tasks[] = [
                'sequence' => $sequence,
                'task_key' => $this->taskKey($profile, $snapshotHash, $analyzer),
                'analyzer_key' => $analyzer->key(),
                'analyzer_version' => $analyzer->version(),
                'depends_on' => $dependencies,
            ];
        }

        return [
            'profile' => $profile,
            'snapshot_hash' => $snapshotHash,
            'tasks' => $tasks,
            'skipped' => $skipped,
        ];
    }

    private function snapshotHash(array $snapshot): string
    {
        $snapshotHash = $snapshot['snapshot_hash'] ?? null;

        return is_string($snapshotHash) && $snapshotHash !== ''
            ? $snapshotHash
            : 'snapshot-hash-missing';
    }

    private function taskKey(string $profile, string $snapshotHash, AnalyzerInterface $analyzer): string
    {
        $hashInput = implode('|', [
            $profile,
            $snapshotHash,
            $analyzer->key(),
            $analyzer->version(),
        ]);

        return sprintf(
            '%s:%s',
            $analyzer->key(),
            substr(hash('sha256', $hashInput), 0, 24),
        );
    }
}
