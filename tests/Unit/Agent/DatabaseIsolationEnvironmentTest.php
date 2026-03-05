<?php

namespace Tests\Unit\Agent;

use App\Support\Agent\DatabaseIsolationEnvironment;
use Tests\TestCase;

class DatabaseIsolationEnvironmentTest extends TestCase
{
    public function test_production_db_credentials_are_stripped_from_base_env(): void
    {
        $baseEnv = [
            'PATH' => '/usr/bin',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => 'prod-host.example.com',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'agent_production',
            'DB_USERNAME' => 'prod_user',
            'DB_PASSWORD' => 'super_secret',
            'DB_URL' => 'postgres://prod_user:secret@prod-host:5432/agent_production',
            'DB_CHARSET' => 'utf8',
            'DB_SSLMODE' => 'require',
            'DB_SEARCH_PATH' => 'public',
            'SOME_SAFE_VAR' => 'keep_me',
        ];

        $result = DatabaseIsolationEnvironment::build($baseEnv);

        $this->assertSame('keep_me', $result['SOME_SAFE_VAR']);
        $this->assertSame('/usr/bin', $result['PATH']);

        $this->assertNotSame('pgsql', $result['DB_CONNECTION']);
        $this->assertSame('pgsql_testing', $result['DB_CONNECTION']);
        $this->assertArrayNotHasKey('DB_HOST', $result);
        $this->assertArrayNotHasKey('DB_PORT', $result);
        $this->assertArrayNotHasKey('DB_DATABASE', $result);
        $this->assertArrayNotHasKey('DB_USERNAME', $result);
        $this->assertArrayNotHasKey('DB_PASSWORD', $result);
        $this->assertArrayNotHasKey('DB_URL', $result);
    }

    public function test_app_key_is_stripped(): void
    {
        $result = DatabaseIsolationEnvironment::build([
            'APP_KEY' => 'base64:secret_key_here',
            'APP_NAME' => 'Agent',
        ]);

        $this->assertArrayNotHasKey('APP_KEY', $result);
        $this->assertSame('Agent', $result['APP_NAME']);
    }

    public function test_safe_testing_vars_are_always_injected(): void
    {
        $result = DatabaseIsolationEnvironment::build([]);

        $this->assertSame('testing', $result['APP_ENV']);
        $this->assertSame('pgsql_testing', $result['DB_CONNECTION']);
        $this->assertSame('1', $result['AGENT_DB_ISOLATED']);
        $this->assertSame('sync', $result['QUEUE_CONNECTION']);
        $this->assertArrayHasKey('TEST_DB_HOST', $result);
        $this->assertArrayHasKey('TEST_DB_PORT', $result);
        $this->assertArrayHasKey('TEST_DB_DATABASE', $result);
        $this->assertArrayHasKey('TEST_DB_USERNAME', $result);
        $this->assertArrayHasKey('TEST_DB_PASSWORD', $result);
    }

    public function test_safe_testing_vars_cannot_be_overridden_by_overrides(): void
    {
        $result = DatabaseIsolationEnvironment::build(
            [],
            [
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'agent_production',
                'DB_HOST' => 'evil-host',
            ]
        );

        $this->assertSame('pgsql_testing', $result['DB_CONNECTION']);
        $this->assertArrayNotHasKey('DB_HOST', $result);
        $this->assertArrayNotHasKey('DB_DATABASE', $result);
    }

    public function test_non_db_overrides_are_applied(): void
    {
        $result = DatabaseIsolationEnvironment::build(
            ['EXISTING_VAR' => 'old_value'],
            ['EXISTING_VAR' => 'new_value', 'CUSTOM_FLAG' => '1']
        );

        $this->assertSame('new_value', $result['EXISTING_VAR']);
        $this->assertSame('1', $result['CUSTOM_FLAG']);
    }

    public function test_non_string_keys_and_non_scalar_values_are_skipped(): void
    {
        $result = DatabaseIsolationEnvironment::build([
            0 => 'numeric_key',
            'VALID' => 'yes',
            'ARRAY_VALUE' => ['not', 'scalar'],
        ]);

        $this->assertSame('yes', $result['VALID']);
        $this->assertArrayNotHasKey(0, $result);
        $this->assertArrayNotHasKey('ARRAY_VALUE', $result);
    }

    public function test_production_db_keys_list_is_complete(): void
    {
        $keys = DatabaseIsolationEnvironment::productionDbKeys();

        $expectedKeys = [
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'DB_URL',
            'DB_CHARSET',
            'DB_SSLMODE',
            'DB_SEARCH_PATH',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertContains($key, $keys, "Missing expected key: {$key}");
        }
    }

    public function test_safe_testing_database_vars_match_pgsql_testing_config(): void
    {
        $vars = DatabaseIsolationEnvironment::safeTestingDatabaseVars();
        $config = config('database.connections.pgsql_testing');

        $this->assertSame(trim((string) $config['host']), $vars['TEST_DB_HOST']);
        $this->assertSame(trim((string) $config['port']), $vars['TEST_DB_PORT']);
        $this->assertSame(trim((string) $config['database']), $vars['TEST_DB_DATABASE']);
    }
}
