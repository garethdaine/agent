<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->assertTestingEnvironmentIsSafeBeforeBoot();

        // Drop the agent_projection schema before the first migrate:fresh.
        //
        // Laravel's db:wipe only drops tables in schemas listed in search_path
        // (typically just "public"). The agent_projection schema — created by
        // raw SQL migrations with CREATE TABLE IF NOT EXISTS — survives across
        // db:wipe calls, leaving stale tables whose column definitions may
        // differ from what the current migrations expect. This causes:
        //   - CREATE INDEX failures on missing columns
        //   - UniqueConstraintViolation from leftover data
        //
        // This runs before parent::setUp() because that is where RefreshDatabase
        // and DatabaseTruncation trigger migrate:fresh on the very first test of
        // a PHP process. A raw PDO connection is used because the Laravel
        // application has not booted yet at this point.
        //
        // Note: this cannot be done via beforeRefreshingDatabase() because the
        // RefreshDatabase trait's empty implementation takes precedence over any
        // parent class method due to PHP trait resolution rules.
        if (! RefreshDatabaseState::$migrated) {
            $this->dropCustomSchemasForFreshMigration();
        }

        parent::setUp();

        $this->assertTestingEnvironmentIsSafeAfterBoot();

        $this->ensureWorkingDirectoryBasesConfigured();

        // Force null broadcaster in tests to prevent real HTTP calls to Reverb.
        // The .env BROADCAST_CONNECTION=reverb can override phpunit.xml's
        // force="true" setting when Herd loads .env before PHPUnit.
        config()->set('broadcasting.default', 'null');
    }

    /**
     * Ensure allowed_working_directory_bases is configured for tests.
     *
     * When the env var AGENT_WORKING_DIRECTORY_BASES is not set (common in
     * CI/testing environments), fall back to base_path() so that tests using
     * base_path() as the project directory pass path-policy validation.
     */
    private function ensureWorkingDirectoryBasesConfigured(): void
    {
        $bases = config('agent.allowed_working_directory_bases', []);

        if (empty($bases)) {
            config()->set('agent.allowed_working_directory_bases', [base_path()]);
        }
    }

    /**
     * Ensure custom PostgreSQL enum types are dropped during migrate:fresh.
     */
    protected function shouldDropTypes()
    {
        return true;
    }

    private function dropCustomSchemasForFreshMigration(): void
    {
        try {
            $database = (string) env('TEST_DB_DATABASE', 'agent_test');

            // When running under ParaTest (--parallel), each worker process
            // receives a TEST_TOKEN env var and operates on its own suffixed
            // database (e.g. agent_test_test_1). We must connect to the
            // correct per-worker database to drop the stale schema.
            $token = getenv('TEST_TOKEN');
            if ($token !== false && $token !== '') {
                $database .= '_test_'.$token;
            }

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                env('TEST_DB_HOST', '127.0.0.1'),
                env('TEST_DB_PORT', '5432'),
                $database,
            );
            $pdo = new \PDO($dsn, env('TEST_DB_USERNAME', 'root'), env('TEST_DB_PASSWORD', ''));
            $pdo->exec('DROP SCHEMA IF EXISTS agent_projection CASCADE');
        } catch (\Throwable) {
            // Ignore — schema may not exist, DB may be unreachable, or
            // the per-worker database may not have been created yet.
        }
    }

    private function assertTestingEnvironmentIsSafeBeforeBoot(): void
    {
        $appEnv = strtolower((string) getenv('APP_ENV'));
        if ($appEnv !== 'testing') {
            throw new RuntimeException(sprintf(
                'Unsafe test bootstrap: APP_ENV must be "testing" (received "%s").',
                $appEnv !== '' ? $appEnv : 'empty'
            ));
        }

        $connection = trim((string) getenv('DB_CONNECTION'));
        if ($connection !== 'pgsql_testing') {
            throw new RuntimeException(sprintf(
                'Unsafe test bootstrap: DB_CONNECTION must be "pgsql_testing" (received "%s").',
                $connection !== '' ? $connection : 'empty'
            ));
        }

        $database = trim((string) getenv('TEST_DB_DATABASE'));
        if (! $this->isSafeTestingDatabaseName($database)) {
            throw new RuntimeException(sprintf(
                'Unsafe test bootstrap: TEST_DB_DATABASE must be an isolated test database (received "%s").',
                $database !== '' ? $database : 'empty'
            ));
        }
    }

    private function assertTestingEnvironmentIsSafeAfterBoot(): void
    {
        $defaultConnection = (string) config('database.default');
        if ($defaultConnection !== 'pgsql_testing') {
            throw new RuntimeException(sprintf(
                'Unsafe test runtime: database.default must be "pgsql_testing" (received "%s").',
                $defaultConnection !== '' ? $defaultConnection : 'empty'
            ));
        }

        $database = (string) config('database.connections.pgsql_testing.database');
        if (! $this->isSafeTestingDatabaseName($database)) {
            throw new RuntimeException(sprintf(
                'Unsafe test runtime: pgsql_testing database must be an isolated test database (received "%s").',
                $database !== '' ? $database : 'empty'
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
}
