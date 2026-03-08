<?php

declare(strict_types=1);

namespace Tests\Unit\Agent;

use App\Support\Agent\DatabaseDestructionGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DatabaseDestructionGuardTest extends TestCase
{
    private DatabaseDestructionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DatabaseDestructionGuard;
    }

    public function test_it_ignores_non_agent_subprocess_context(): void
    {
        $this->guard->enforce(
            ['php', 'artisan', 'migrate:fresh'],
            ['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'agent']
        );

        $this->assertTrue(true);
    }

    public function test_it_ignores_non_artisan_commands_in_agent_context(): void
    {
        $this->guard->enforce(
            ['php', 'some_script.php'],
            ['AGENT_DB_ISOLATED' => '1', 'DB_CONNECTION' => 'pgsql_testing', 'TEST_DB_DATABASE' => 'agent_test']
        );

        $this->assertTrue(true);
    }

    public function test_it_allows_destructive_commands_against_test_database(): void
    {
        $this->guard->enforce(
            ['php', 'artisan', 'migrate:fresh'],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql_testing',
                'TEST_DB_DATABASE' => 'agent_test',
            ]
        );

        $this->assertTrue(true);
    }

    public function test_it_allows_safe_artisan_commands_in_agent_context(): void
    {
        $safeCommands = ['migrate', 'route:list', 'config:clear', 'test'];

        foreach ($safeCommands as $command) {
            $this->guard->enforce(
                ['php', 'artisan', $command],
                [
                    'AGENT_DB_ISOLATED' => '1',
                    'DB_CONNECTION' => 'pgsql_testing',
                    'TEST_DB_DATABASE' => 'agent_test',
                ]
            );
        }

        $this->assertTrue(true);
    }

    #[DataProvider('destructiveCommandProvider')]
    public function test_it_blocks_destructive_commands_against_non_test_database(string $command): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database safety violation');

        $this->guard->enforce(
            ['php', 'artisan', $command],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql_testing',
                'TEST_DB_DATABASE' => 'agent_production',
            ]
        );
    }

    public static function destructiveCommandProvider(): array
    {
        return [
            'migrate:fresh' => ['migrate:fresh'],
            'migrate:refresh' => ['migrate:refresh'],
            'migrate:reset' => ['migrate:reset'],
            'db:wipe' => ['db:wipe'],
        ];
    }

    public function test_it_blocks_non_pgsql_testing_connection_in_agent_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_CONNECTION must be "pgsql_testing"');

        $this->guard->enforce(
            ['php', 'artisan', 'migrate'],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql',
                'TEST_DB_DATABASE' => 'agent_test',
            ]
        );
    }

    public function test_it_blocks_unsafe_test_db_name_in_agent_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TEST_DB_DATABASE must be an isolated test database');

        $this->guard->enforce(
            ['php', 'artisan', 'migrate'],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql_testing',
                'TEST_DB_DATABASE' => 'agent_production',
            ]
        );
    }

    #[DataProvider('safeTestDatabaseNameProvider')]
    public function test_it_allows_safe_test_database_names(string $name): void
    {
        $this->guard->enforce(
            ['php', 'artisan', 'migrate:fresh'],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql_testing',
                'TEST_DB_DATABASE' => $name,
            ]
        );

        $this->assertTrue(true);
    }

    public static function safeTestDatabaseNameProvider(): array
    {
        return [
            'agent_test' => ['agent_test'],
            'agent_testing' => ['agent_testing'],
            'test_agent' => ['test_agent'],
            'testing_db' => ['testing_db'],
            'my_test_db' => ['my_test_db'],
        ];
    }

    #[DataProvider('unsafeTestDatabaseNameProvider')]
    public function test_it_blocks_unsafe_database_names(string $name): void
    {
        $this->expectException(RuntimeException::class);

        $this->guard->enforce(
            ['php', 'artisan', 'migrate:fresh'],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql_testing',
                'TEST_DB_DATABASE' => $name,
            ]
        );
    }

    public static function unsafeTestDatabaseNameProvider(): array
    {
        return [
            'agent' => ['agent'],
            'agent_production' => ['agent_production'],
            'production' => ['production'],
            'empty string' => [''],
            'contest (contains test but not as word boundary)' => ['contest'],
        ];
    }

    public function test_it_handles_destructive_command_with_flags(): void
    {
        $this->guard->enforce(
            ['php', 'artisan', '--env=testing', 'migrate:fresh', '--seed'],
            [
                'AGENT_DB_ISOLATED' => '1',
                'DB_CONNECTION' => 'pgsql_testing',
                'TEST_DB_DATABASE' => 'agent_test',
            ]
        );

        $this->assertTrue(true);
    }
}
