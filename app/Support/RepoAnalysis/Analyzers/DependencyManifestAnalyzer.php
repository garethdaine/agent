<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class DependencyManifestAnalyzer extends AbstractAnalyzer
{
    /**
     * @var array<int, string>
     */
    private const MANIFEST_FILES = [
        'composer.json',
        'package.json',
    ];

    /**
     * @var array<int, string>
     */
    private const LOCK_FILES = [
        'composer.lock',
        'package-lock.json',
        'pnpm-lock.yaml',
        'yarn.lock',
    ];

    public function key(): string
    {
        return 'dependency_manifest';
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
        return ['filesystem_manifest'];
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);
        $warnings = [];

        $manifestFiles = array_values(array_intersect(self::MANIFEST_FILES, $paths));
        sort($manifestFiles, SORT_STRING);
        $lockFiles = array_values(array_intersect(self::LOCK_FILES, $paths));
        sort($lockFiles, SORT_STRING);

        if ($manifestFiles === [] && $lockFiles === []) {
            $warnings[] = [
                'code' => 'missing_manifests',
                'message' => 'No dependency manifests or lockfiles were found.',
            ];
        }

        foreach ($manifestFiles as $manifestFile) {
            $content = $this->fileContentByPath($snapshot, $manifestFile);
            if ($content === null) {
                continue;
            }

            json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $warnings[] = [
                    'code' => 'parser_error',
                    'message' => sprintf('Unable to parse %s as JSON.', $manifestFile),
                    'file' => $manifestFile,
                ];
            }
        }

        $ecosystems = [];
        if (in_array('composer.json', $manifestFiles, true) || in_array('composer.lock', $lockFiles, true)) {
            $ecosystems[] = 'php';
        }
        if (in_array('package.json', $manifestFiles, true)
            || in_array('package-lock.json', $lockFiles, true)
            || in_array('pnpm-lock.yaml', $lockFiles, true)
            || in_array('yarn.lock', $lockFiles, true)) {
            $ecosystems[] = 'node';
        }
        sort($ecosystems, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'manifests' => $manifestFiles,
            'lockfiles' => $lockFiles,
            'ecosystems' => array_values(array_unique($ecosystems)),
        ], $warnings);
    }
}
