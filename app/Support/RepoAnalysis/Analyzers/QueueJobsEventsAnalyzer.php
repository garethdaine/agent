<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class QueueJobsEventsAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'queue_jobs_events';
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
        return ['laravel_models_migrations'];
    }

    public function supports(array $snapshot): bool
    {
        $paths = $this->snapshotPaths($snapshot);

        foreach ($paths as $path) {
            if (str_starts_with($path, 'app/Jobs/') || str_starts_with($path, 'app/Events/')) {
                return true;
            }
        }

        return false;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $jobFiles = array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_starts_with($path, 'app/Jobs/')
        ));
        sort($jobFiles, SORT_STRING);

        $eventFiles = array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_starts_with($path, 'app/Events/')
        ));
        sort($eventFiles, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'job_files' => $jobFiles,
            'event_files' => $eventFiles,
            'job_count' => count($jobFiles),
            'event_count' => count($eventFiles),
        ]);
    }
}
