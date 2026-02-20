<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->assertTestingEnvironmentIsSafeBeforeBoot();

        parent::setUp();

        $this->assertTestingEnvironmentIsSafeAfterBoot();
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
