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
        'pnpm-workspace.yaml',
        'bunfig.toml',
        'deno.json',
        'deno.jsonc',
        'pyproject.toml',
        'requirements.txt',
        'requirements-dev.txt',
        'setup.py',
        'setup.cfg',
        'pipfile',
        'environment.yml',
        'environment.yaml',
        'poetry.toml',
        'go.mod',
        'cargo.toml',
        'gemfile',
        'pom.xml',
        'build.gradle',
        'build.gradle.kts',
        'settings.gradle',
        'settings.gradle.kts',
        'mix.exs',
        'package.swift',
        'podfile',
        'cartfile',
        'pubspec.yaml',
        'vcpkg.json',
        'conanfile.py',
        'conanfile.txt',
        'nuget.config',
        'packages.config',
    ];

    /**
     * @var array<int, string>
     */
    private const LOCK_FILES = [
        'composer.lock',
        'package-lock.json',
        'pnpm-lock.yaml',
        'yarn.lock',
        'bun.lockb',
        'poetry.lock',
        'pipfile.lock',
        'go.sum',
        'cargo.lock',
        'gemfile.lock',
        'mix.lock',
        'podfile.lock',
        'cartfile.resolved',
        'pubspec.lock',
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

        $manifestFiles = [];
        $lockFiles = [];
        foreach ($paths as $path) {
            $normalizedPath = strtolower(str_replace('\\', '/', $path));
            $basename = basename($normalizedPath);

            if ($this->isManifestPath($normalizedPath, $basename)) {
                $manifestFiles[] = $path;
            }

            if ($this->isLockPath($normalizedPath, $basename)) {
                $lockFiles[] = $path;
            }
        }
        $manifestFiles = array_values(array_unique($manifestFiles));
        sort($manifestFiles, SORT_STRING);

        $lockFiles = array_values(array_unique($lockFiles));
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

            $isJsonManifest = str_ends_with(strtolower($manifestFile), '.json');
            if (! $isJsonManifest) {
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
        $normalizedManifestBasenames = array_map(
            static fn (string $value): string => basename(strtolower(str_replace('\\', '/', $value))),
            $manifestFiles
        );
        $normalizedLockBasenames = array_map(
            static fn (string $value): string => basename(strtolower(str_replace('\\', '/', $value))),
            $lockFiles
        );

        $manifestSet = array_fill_keys($normalizedManifestBasenames, true);
        $lockSet = array_fill_keys($normalizedLockBasenames, true);

        if (isset($manifestSet['composer.json']) || isset($lockSet['composer.lock'])) {
            $ecosystems[] = 'php';
        }
        if (isset($manifestSet['package.json'])
            || isset($manifestSet['pnpm-workspace.yaml'])
            || isset($manifestSet['bunfig.toml'])
            || isset($lockSet['package-lock.json'])
            || isset($lockSet['pnpm-lock.yaml'])
            || isset($lockSet['yarn.lock'])
            || isset($lockSet['bun.lockb'])) {
            $ecosystems[] = 'javascript';
        }
        if (isset($manifestSet['pyproject.toml'])
            || isset($manifestSet['requirements.txt'])
            || isset($manifestSet['requirements-dev.txt'])
            || isset($manifestSet['setup.py'])
            || isset($manifestSet['setup.cfg'])
            || isset($manifestSet['pipfile'])
            || isset($manifestSet['environment.yml'])
            || isset($manifestSet['environment.yaml'])
            || isset($manifestSet['poetry.toml'])
            || isset($lockSet['poetry.lock'])
            || isset($lockSet['pipfile.lock'])) {
            $ecosystems[] = 'python';
        }
        if (isset($manifestSet['go.mod']) || isset($lockSet['go.sum'])) {
            $ecosystems[] = 'go';
        }
        if (isset($manifestSet['cargo.toml']) || isset($lockSet['cargo.lock'])) {
            $ecosystems[] = 'rust';
        }
        if (isset($manifestSet['gemfile']) || isset($lockSet['gemfile.lock'])) {
            $ecosystems[] = 'ruby';
        }
        if (isset($manifestSet['pom.xml'])
            || isset($manifestSet['build.gradle'])
            || isset($manifestSet['build.gradle.kts'])
            || isset($manifestSet['settings.gradle'])
            || isset($manifestSet['settings.gradle.kts'])) {
            $ecosystems[] = 'jvm';
        }
        if (isset($manifestSet['deno.json']) || isset($manifestSet['deno.jsonc'])) {
            $ecosystems[] = 'deno';
        }
        if (isset($manifestSet['mix.exs']) || isset($lockSet['mix.lock'])) {
            $ecosystems[] = 'elixir';
        }
        if (isset($manifestSet['package.swift'])
            || isset($manifestSet['podfile'])
            || isset($manifestSet['cartfile'])
            || isset($lockSet['podfile.lock'])
            || isset($lockSet['cartfile.resolved'])) {
            $ecosystems[] = 'swift';
        }
        if (isset($manifestSet['pubspec.yaml']) || isset($lockSet['pubspec.lock'])) {
            $ecosystems[] = 'dart_flutter';
        }
        if (isset($manifestSet['vcpkg.json'])
            || isset($manifestSet['conanfile.py'])
            || isset($manifestSet['conanfile.txt'])) {
            $ecosystems[] = 'cpp';
        }

        foreach (array_merge($normalizedManifestBasenames, $normalizedLockBasenames) as $basename) {
            if (preg_match('/\.(csproj|fsproj|vbproj)$/', $basename) === 1
                || $basename === '.sln'
                || $basename === 'packages.config'
                || $basename === 'nuget.config') {
                $ecosystems[] = 'dotnet';
                break;
            }
        }

        sort($ecosystems, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'manifests' => $manifestFiles,
            'lockfiles' => $lockFiles,
            'ecosystems' => array_values(array_unique($ecosystems)),
        ], $warnings);
    }

    private function isManifestPath(string $normalizedPath, string $basename): bool
    {
        if (in_array($basename, self::MANIFEST_FILES, true)) {
            return true;
        }

        return preg_match('/\.(csproj|fsproj|vbproj|sln)$/', $basename) === 1;
    }

    private function isLockPath(string $normalizedPath, string $basename): bool
    {
        if (in_array($basename, self::LOCK_FILES, true)) {
            return true;
        }

        return str_ends_with($normalizedPath, '/gradle.lockfile') || $basename === 'gradle.lockfile';
    }
}
