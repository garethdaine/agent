<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class AsyncWorkflowsSurfaceAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'async_workflows_surface';
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
        return ['data_model_surface'];
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $jobFiles = array_values(array_filter(
            $paths,
            static function (string $path): bool {
                $normalized = strtolower(str_replace('\\', '/', $path));
                $basename = basename($normalized);

                if (str_contains($normalized, '/jobs/')
                    || str_contains($normalized, '/job/')
                    || str_contains($normalized, '/workers/')
                    || str_contains($normalized, '/queue/')
                    || str_contains($normalized, '/tasks/')
                    || str_contains($normalized, '/consumers/')
                    || str_contains($normalized, '/handlers/')) {
                    return true;
                }

                return preg_match('/(?:^|[._-])(job|worker|queue|task|consumer|processor)(?:[._-]|$)/i', $basename) === 1;
            }
        ));
        sort($jobFiles, SORT_STRING);

        $eventFiles = array_values(array_filter(
            $paths,
            static function (string $path): bool {
                $normalized = strtolower(str_replace('\\', '/', $path));
                $basename = basename($normalized);

                if (str_contains($normalized, '/events/')
                    || str_contains($normalized, '/messages/')
                    || str_contains($normalized, '/pubsub/')
                    || str_contains($normalized, '/subscribers/')
                    || str_contains($normalized, '/listeners/')) {
                    return true;
                }

                return preg_match('/(?:^|[._-])(event|message|subscriber|listener|webhook)(?:[._-]|$)/i', $basename) === 1;
            }
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
