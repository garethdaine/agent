<?php

namespace App\Support\Agent;

use RuntimeException;

class DatabaseDestructionGuard
{
    /**
     * @var array<int, string>
     */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    /**
     * Enforce from the current process globals.
     * Intended to be called early in the boot sequence.
     */
    public function enforceFromGlobals(): void
    {
        $environment = array_merge(
            is_array($_SERVER) ? $_SERVER : [],
            is_array($_ENV) ? $_ENV : [],
        );

        $this->enforce(
            is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [],
            $environment
        );
    }

    /**
     * @param  array<int, mixed>  $argv
     * @param  array<string, mixed>  $environment
     */
    public function enforce(array $argv, array $environment = []): void
    {
        if (! $this->isAgentSubprocess($environment)) {
            return;
        }

        $command = $this->resolveArtisanCommand($argv);
        if ($command === null) {
            return;
        }

        $this->assertDatabaseIsSafe($environment);

        if (in_array($command, self::DESTRUCTIVE_COMMANDS, true)) {
            $database = $this->resolveEnv('TEST_DB_DATABASE', $environment);
            if (! $this->isSafeTestingDatabaseName($database)) {
                throw new RuntimeException(sprintf(
                    'Database safety violation: destructive command [%s] blocked — target database "%s" is not a recognised testing database.',
                    $command,
                    $database !== '' ? $database : 'empty'
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function isAgentSubprocess(array $environment): bool
    {
        return $this->resolveEnv('AGENT_DB_ISOLATED', $environment) === '1';
    }

    /**
     * @param  array<string, mixed>  $environment
     */
    private function assertDatabaseIsSafe(array $environment): void
    {
        $connection = strtolower($this->resolveEnv('DB_CONNECTION', $environment));
        if ($connection !== '' && $connection !== 'pgsql_testing') {
            throw new RuntimeException(sprintf(
                'Database safety violation: DB_CONNECTION must be "pgsql_testing" in agent subprocesses (received "%s").',
                $connection
            ));
        }

        $testDatabase = $this->resolveEnv('TEST_DB_DATABASE', $environment);
        if ($testDatabase !== '' && ! $this->isSafeTestingDatabaseName($testDatabase)) {
            throw new RuntimeException(sprintf(
                'Database safety violation: TEST_DB_DATABASE must be an isolated test database (received "%s").',
                $testDatabase
            ));
        }
    }

    private function isSafeTestingDatabaseName(string $database): bool
    {
        if ($database === '') {
            return false;
        }

        return (bool) preg_match('/(^|_)(test|testing)(_|$)/i', $database);
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
}
