<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Interrogation\InterrogationBuildCommandGuard;
use RuntimeException;
use Tests\TestCase;

class InterrogationBuildCommandGuardTest extends TestCase
{
    public function test_it_ignores_non_interrogation_commands(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);

        $guard->enforce(
            ['php', 'artisan', 'migrate:fresh'],
            [
                'AGENT_JOB_SOURCE' => 'manual',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'agent',
            ],
        );

        $this->assertTrue(true);
    }

    public function test_it_blocks_destructive_artisan_commands_for_interrogation_build_runs(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);
        $path = $this->sandboxDatabasePath('blocked.sqlite');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migrate:fresh');

        $guard->enforce(
            ['php', 'artisan', 'migrate:fresh', '--seed'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $path,
            ],
        );
    }

    public function test_it_requires_sqlite_or_pgsql_testing_connection_for_interrogation_build_runs(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_CONNECTION must be sqlite or pgsql_testing');

        $guard->enforce(
            ['php', 'artisan', 'test'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'agent',
            ],
        );
    }

    public function test_it_requires_sqlite_database_path_inside_sandbox(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);
        $outsidePath = storage_path('framework/testing/outside-interrogation.sqlite');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_DATABASE must be inside');

        $guard->enforce(
            ['php', 'artisan', 'test'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $outsidePath,
            ],
        );
    }

    public function test_it_allows_safe_artisan_commands_with_isolated_sqlite_database(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);
        $path = $this->sandboxDatabasePath('allowed.sqlite');

        $guard->enforce(
            ['php', 'artisan', 'test'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $path,
            ],
        );

        $this->assertTrue(true);
    }

    public function test_it_allows_safe_artisan_test_commands_with_isolated_pgsql_testing_database(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);

        $guard->enforce(
            ['php', 'artisan', 'test'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'pgsql_testing',
                'DB_DATABASE' => '/tmp/interrogation-sentinel.sqlite',
                'TEST_DB_DATABASE' => 'agent_test',
            ],
        );

        $this->assertTrue(true);
    }

    public function test_it_rejects_non_isolated_pgsql_testing_database_name(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST_DB_DATABASE must be an isolated test database');

        $guard->enforce(
            ['php', 'artisan', 'test'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'pgsql_testing',
                'DB_DATABASE' => '/tmp/interrogation-sentinel.sqlite',
                'TEST_DB_DATABASE' => 'agent',
            ],
        );
    }

    public function test_it_rejects_pgsql_testing_when_test_database_matches_primary_database(): void
    {
        $guard = app(InterrogationBuildCommandGuard::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST_DB_DATABASE must differ from DB_DATABASE');

        $guard->enforce(
            ['php', 'artisan', 'test'],
            [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
                'APP_ENV' => 'production',
                'DB_CONNECTION' => 'pgsql_testing',
                'DB_DATABASE' => 'agent_test',
                'TEST_DB_DATABASE' => 'agent_test',
            ],
        );
    }

    private function sandboxDatabasePath(string $fileName): string
    {
        $directory = storage_path('framework/interrogation-build');
        @mkdir($directory, 0777, true);

        $path = $directory.'/'.$fileName;
        @touch($path);

        return $path;
    }
}
