<?php

namespace App\Support\Agent;

class DatabaseIsolationEnvironment
{
    /**
     * Keys that carry production database credentials and must never
     * leak into agent subprocesses.
     *
     * @var array<int, string>
     */
    private const PRODUCTION_DB_KEYS = [
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

    /**
     * Keys that must be explicitly suppressed in subprocesses.
     *
     * Symfony Process merges getenv()/\$_SERVER back into the env array
     * via += for any missing keys.  Simply omitting a key is not enough;
     * we must set it to `false` so the key is present (blocking the
     * backfill) while Symfony skips false values when building envPairs.
     *
     * @var array<int, string>
     */
    private const SUPPRESSED_KEYS = [
        'CLAUDECODE',
    ];

    /**
     * Build a subprocess environment from $_ENV with production DB
     * credentials stripped and safe testing values injected.
     *
     * @param  array<string, mixed>  $baseEnv  Typically $_ENV
     * @param  array<string, mixed>  $overrides  Job-level env_json
     * @return array<string, string|false>
     */
    public static function build(array $baseEnv, array $overrides = []): array
    {
        $env = [];
        $stripped = array_merge(self::PRODUCTION_DB_KEYS, ['APP_KEY'], self::SUPPRESSED_KEYS);

        foreach ($baseEnv as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            if (in_array($key, $stripped, true)) {
                continue;
            }

            $env[$key] = (string) $value;
        }

        foreach ($overrides as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            if (in_array($key, self::PRODUCTION_DB_KEYS, true)) {
                continue;
            }

            $env[$key] = (string) $value;
        }

        $env = array_merge($env, self::safeTestingDatabaseVars());

        // Set suppressed keys to false so Symfony Process sees them as
        // present (preventing getDefaultEnv() backfill via +=) but skips
        // them when building the envPairs array for proc_open().
        foreach (self::SUPPRESSED_KEYS as $key) {
            $env[$key] = false;
        }

        return $env;
    }

    /**
     * @return array<string, string>
     */
    public static function safeTestingDatabaseVars(): array
    {
        $testing = (array) config('database.connections.pgsql_testing', []);

        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql_testing',
            'TEST_DB_HOST' => trim((string) ($testing['host'] ?? '127.0.0.1')),
            'TEST_DB_PORT' => trim((string) ($testing['port'] ?? '5432')),
            'TEST_DB_DATABASE' => trim((string) ($testing['database'] ?? 'agent_test')),
            'TEST_DB_USERNAME' => trim((string) ($testing['username'] ?? 'root')),
            'TEST_DB_PASSWORD' => (string) ($testing['password'] ?? ''),
            'TEST_DB_CHARSET' => trim((string) ($testing['charset'] ?? 'utf8')),
            'TEST_DB_SEARCH_PATH' => trim((string) ($testing['search_path'] ?? 'public')),
            'TEST_DB_SSLMODE' => trim((string) ($testing['sslmode'] ?? 'prefer')),
            'AGENT_DB_ISOLATED' => '1',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function productionDbKeys(): array
    {
        return self::PRODUCTION_DB_KEYS;
    }
}
