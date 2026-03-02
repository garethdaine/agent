<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class FilesystemManifestAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'filesystem_manifest';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'file_count' => count($paths),
            'paths' => $paths,
        ]);
    }
}
