<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use RuntimeException;

class InterrogationBuildCommandGuard
{
    /**
     * @var array<int, string>
     */
    private const BLOCKED_ARTISAN_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    public function enforceFromGlobals(): void
    {
        $environment = array_merge(
            is_array($_SERVER) ? $_SERVER : [],
            is_array($_ENV) ? $_ENV : [],
        );

        $this->enforce(is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [], $environment);
    }

    /**
     * @param  array<int, mixed>  $argv
     * @param  array<string, mixed>  $environment
     */
    public function enforce(array $argv, array $environment = []): void
    {
        if ($this->resolveEnv('AGENT_JOB_SOURCE', $environment) !== 'interrogation_build') {
            return;
        }

        $command = $this->resolveArtisanCommand($argv);
        if ($command === null) {
            return;
        }

        $this->assertIsolatedDatabase($environment);

        if (in_array($command, self::BLOCKED_ARTISAN_COMMANDS, true)) {
            throw new RuntimeException(sprintf(
                'Interrogation build safety violation: artisan command [%s] is blocked for build execution runs.',
                $command
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function assertIsolatedDatabase(array $environment): void
    {
        $connection = strtolower($this->resolveEnv('DB_CONNECTION', $environment));
        $database = $this->resolveEnv('DB_DATABASE', $environment);

        if ($connection === 'sqlite') {
            $this->assertIsolatedSqliteDatabase($database);

            return;
        }

        if ($connection === 'pgsql_testing') {
            $this->assertIsolatedPgsqlTestingDatabase($environment);

            return;
        }

        throw new RuntimeException(
            'Interrogation build safety violation: DB_CONNECTION must be sqlite or pgsql_testing during build execution.'
        );
    }

    private function assertIsolatedSqliteDatabase(string $database): void
    {
        $sandboxBase = storage_path('framework/interrogation-build');
        if (! is_dir($sandboxBase)) {
            @mkdir($sandboxBase, 0777, true);
        }

        $resolvedBase = realpath($sandboxBase) ?: $sandboxBase;
        $normalizedDatabase = $this->normalizeAbsolutePath($database);
        $prefix = rtrim($resolvedBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($normalizedDatabase === null || ! str_starts_with($normalizedDatabase, $prefix)) {
            throw new RuntimeException(sprintf(
                'Interrogation build safety violation: DB_DATABASE must be inside [%s].',
                $resolvedBase
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function assertIsolatedPgsqlTestingDatabase(array $environment): void
    {
        $testDatabase = strtolower($this->resolveEnv('TEST_DB_DATABASE', $environment));
        $primaryDatabase = strtolower($this->resolveEnv('DB_DATABASE', $environment));

        if ($testDatabase === '' || ! preg_match('/(?:^|[_-])(test|testing)(?:$|[_-])|test|testing/', $testDatabase)) {
            throw new RuntimeException(sprintf(
                'Interrogation build safety violation: TEST_DB_DATABASE must be an isolated test database (received "%s").',
                $testDatabase !== '' ? $testDatabase : 'empty'
            ));
        }

        if ($primaryDatabase !== '' && $primaryDatabase === $testDatabase) {
            throw new RuntimeException('Interrogation build safety violation: TEST_DB_DATABASE must differ from DB_DATABASE.');
        }
    }

    /**
     * @param  array<int, mixed>  $argv
     */
    private function resolveArtisanCommand(array $argv): ?string
    {
        $artisanIndex = null;

        foreach ($argv as $index => $argument) {
            if (! is_string($argument)) {
                continue;
            }

            if (basename($argument) === 'artisan') {
                $artisanIndex = $index;
                break;
            }
        }

        if ($artisanIndex === null) {
            return null;
        }

        for ($index = $artisanIndex + 1; $index < count($argv); $index++) {
            $argument = $argv[$index];
            if (! is_string($argument)) {
                continue;
            }

            $value = trim($argument);
            if ($value === '' || str_starts_with($value, '-')) {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function resolveEnv(string $key, array $environment): string
    {
        $value = $environment[$key] ?? getenv($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function normalizeAbsolutePath(string $path): ?string
    {
        if ($path === '' || ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return null;
        }

        $resolvedPath = realpath($path);
        if ($resolvedPath !== false) {
            return $resolvedPath;
        }

        $resolvedDirectory = realpath(dirname($path));
        if ($resolvedDirectory === false) {
            return null;
        }

        return rtrim($resolvedDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($path);
    }
}
