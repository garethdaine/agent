<?php

namespace Tests\Unit\Agent;

use App\Support\Agent\EnvPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnvPolicyDatabaseKeysTest extends TestCase
{
    private EnvPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new EnvPolicy;
    }

    #[DataProvider('forbiddenDatabaseKeyProvider')]
    public function test_it_rejects_database_env_keys(string $key): void
    {
        $errors = $this->policy->validate([$key => 'some_value']);

        $this->assertArrayHasKey("env_json.{$key}", $errors);
        $this->assertStringContainsString('not allowed', $errors["env_json.{$key}"]);
    }

    public static function forbiddenDatabaseKeyProvider(): array
    {
        return [
            'DB_CONNECTION' => ['DB_CONNECTION'],
            'DB_HOST' => ['DB_HOST'],
            'DB_PORT' => ['DB_PORT'],
            'DB_DATABASE' => ['DB_DATABASE'],
            'DB_USERNAME' => ['DB_USERNAME'],
            'DB_PASSWORD' => ['DB_PASSWORD'],
            'DB_URL' => ['DB_URL'],
            'DB_CHARSET' => ['DB_CHARSET'],
            'DB_SSLMODE' => ['DB_SSLMODE'],
            'DB_SEARCH_PATH' => ['DB_SEARCH_PATH'],
            'TEST_DB_HOST' => ['TEST_DB_HOST'],
            'TEST_DB_PORT' => ['TEST_DB_PORT'],
            'TEST_DB_DATABASE' => ['TEST_DB_DATABASE'],
            'TEST_DB_USERNAME' => ['TEST_DB_USERNAME'],
        ];
    }

    public function test_it_allows_non_database_env_keys(): void
    {
        $errors = $this->policy->validate([
            'CUSTOM_FLAG' => '1',
            'MY_APP_SETTING' => 'value',
            'FEATURE_TOGGLE' => 'on',
        ]);

        $this->assertEmpty($errors);
    }
}
