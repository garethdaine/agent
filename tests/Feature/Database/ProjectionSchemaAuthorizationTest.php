<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectionSchemaAuthorizationTest extends TestCase
{
    use DatabaseMigrations;

    /** @var list<string> */
    private array $rolesToDrop = [];

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->dropTransientProbeRelations();

            foreach ($this->rolesToDrop as $role) {
                $this->dropRole($role);
            }
        }

        parent::tearDown();
    }

    public function test_migration_creates_projection_schema_and_moves_known_tables(): void
    {
        $this->requirePostgres();
        $this->dropTransientProbeRelations();

        DB::statement('CREATE TABLE public.run_attempts (id BIGINT PRIMARY KEY)');

        [$appRole, $projectorRole, $adminRole, $reportingRole] = $this->createRoles();

        $this->applyProjectionSchemaMigration([
            'AGENT_DB_APP_ROLE' => $appRole,
            'AGENT_DB_PROJECTOR_ROLE' => $projectorRole,
            'AGENT_DB_ADMIN_ROLE' => $adminRole,
            'AGENT_DB_REPORTING_ROLES' => $reportingRole,
        ]);

        $this->assertTrue($this->schemaExists('agent_projection'));
        $this->assertFalse($this->relationExists('public', 'run_attempts'));
        $this->assertTrue($this->relationExists('agent_projection', 'run_attempts'));
    }

    public function test_projection_role_permissions_enforce_reporting_denial_and_least_privilege(): void
    {
        $this->requirePostgres();
        $this->dropTransientProbeRelations();

        [$appRole, $projectorRole, $adminRole, $reportingRole] = $this->createRoles();

        $this->applyProjectionSchemaMigration([
            'AGENT_DB_APP_ROLE' => $appRole,
            'AGENT_DB_PROJECTOR_ROLE' => $projectorRole,
            'AGENT_DB_ADMIN_ROLE' => $adminRole,
            'AGENT_DB_REPORTING_ROLES' => $reportingRole,
        ]);

        DB::statement('CREATE TABLE agent_projection.projection_auth_probe (id BIGINT PRIMARY KEY, payload TEXT NOT NULL)');

        $this->assertRoleTablePrivilege($reportingRole, 'agent_projection.projection_auth_probe', 'SELECT', false);
        $this->assertRoleTablePrivilege($reportingRole, 'agent_projection.projection_auth_probe', 'INSERT', false);
        $this->assertRoleTablePrivilege($reportingRole, 'agent_projection.projection_auth_probe', 'UPDATE', false);
        $this->assertRoleTablePrivilege($reportingRole, 'agent_projection.projection_auth_probe', 'DELETE', false);

        $this->assertRoleTablePrivilege($appRole, 'agent_projection.projection_auth_probe', 'SELECT', true);
        $this->assertRoleTablePrivilege($appRole, 'agent_projection.projection_auth_probe', 'INSERT', false);
        $this->assertRoleTablePrivilege($appRole, 'agent_projection.projection_auth_probe', 'UPDATE', false);
        $this->assertRoleTablePrivilege($appRole, 'agent_projection.projection_auth_probe', 'DELETE', false);

        $this->assertRoleTablePrivilege($projectorRole, 'agent_projection.projection_auth_probe', 'INSERT', true);
        $this->assertRoleTablePrivilege($projectorRole, 'agent_projection.projection_auth_probe', 'UPDATE', true);
        $this->assertRoleTablePrivilege($projectorRole, 'agent_projection.projection_auth_probe', 'SELECT', true);
        $this->assertRoleTablePrivilege($projectorRole, 'agent_projection.projection_auth_probe', 'DELETE', false);

        $this->assertRoleTablePrivilege($adminRole, 'agent_projection.projection_auth_probe', 'SELECT', true);
        $this->assertRoleTablePrivilege($adminRole, 'agent_projection.projection_auth_probe', 'INSERT', true);
        $this->assertRoleTablePrivilege($adminRole, 'agent_projection.projection_auth_probe', 'UPDATE', true);
        $this->assertRoleTablePrivilege($adminRole, 'agent_projection.projection_auth_probe', 'DELETE', true);

        $this->expectRoleSelectToFail($reportingRole, 'agent_projection.projection_auth_probe');

        $this->runAsRole($appRole, static function (): void {
            DB::select('SELECT id, payload FROM agent_projection.projection_auth_probe LIMIT 1');
        });

        $this->expectRoleInsertToFail($appRole, 'agent_projection.projection_auth_probe');

        $this->runAsRole($projectorRole, static function (): void {
            DB::insert("INSERT INTO agent_projection.projection_auth_probe (id, payload) VALUES (10, 'seed')");
            DB::update("UPDATE agent_projection.projection_auth_probe SET payload = 'updated' WHERE id = 10");
        });

        $this->runAsRole($adminRole, static function (): void {
            DB::delete('DELETE FROM agent_projection.projection_auth_probe WHERE id = 10');
        });
    }

    private function applyProjectionSchemaMigration(array $envOverrides): void
    {
        $migrationPath = database_path('migrations/2026_03_04_140000_harden_projection_schema_boundary.php');

        $this->assertFileExists($migrationPath, 'Expected projection schema migration file to exist.');

        $originalValues = [];
        foreach ($envOverrides as $envKey => $value) {
            $originalValues[$envKey] = getenv($envKey);
            putenv($envKey.'='.$value);
            $_ENV[$envKey] = $value;
            $_SERVER[$envKey] = $value;
        }

        try {
            $migration = require $migrationPath;
            $migration->up();
        } finally {
            foreach ($envOverrides as $envKey => $_value) {
                $previous = $originalValues[$envKey];
                if ($previous === false || $previous === null) {
                    putenv($envKey);
                    unset($_ENV[$envKey], $_SERVER[$envKey]);
                } else {
                    putenv($envKey.'='.$previous);
                    $_ENV[$envKey] = $previous;
                    $_SERVER[$envKey] = $previous;
                }
            }
        }
    }

    /**
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function createRoles(): array
    {
        $suffix = strtolower((string) str()->uuid());

        $appRole = 'agent_test_app_'.$suffix;
        $projectorRole = 'agent_test_projector_'.$suffix;
        $adminRole = 'agent_test_admin_'.$suffix;
        $reportingRole = 'agent_test_reporting_'.$suffix;

        foreach ([$appRole, $projectorRole, $adminRole, $reportingRole] as $role) {
            $this->createRole($role);
            $this->rolesToDrop[] = $role;
        }

        return [$appRole, $projectorRole, $adminRole, $reportingRole];
    }

    private function createRole(string $role): void
    {
        $roleLiteral = $this->quoteLiteral($role);
        $roleIdentifier = $this->quoteIdentifier($role);

        DB::unprepared(<<<SQL
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = {$roleLiteral}) THEN
                    CREATE ROLE {$roleIdentifier} NOLOGIN;
                END IF;
            END
            $$;
        SQL);
    }

    private function dropRole(string $role): void
    {
        if (! $this->roleExists($role)) {
            return;
        }

        DB::statement('DROP OWNED BY '.$this->quoteIdentifier($role));
        DB::statement('DROP ROLE '.$this->quoteIdentifier($role));
    }

    private function schemaExists(string $schema): bool
    {
        $row = DB::selectOne(
            'SELECT EXISTS(SELECT 1 FROM pg_namespace WHERE nspname = ?) AS schema_exists',
            [$schema]
        );

        return $this->toBool($row?->schema_exists ?? null);
    }

    private function relationExists(string $schema, string $relation): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
                SELECT EXISTS (
                    SELECT 1
                    FROM pg_class c
                    INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = ?
                      AND c.relname = ?
                      AND c.relkind IN ('r', 'p', 'v', 'm')
                ) AS relation_exists
            SQL,
            [$schema, $relation]
        );

        return $this->toBool($row?->relation_exists ?? null);
    }

    private function assertRoleTablePrivilege(string $role, string $relation, string $privilege, bool $expected): void
    {
        $row = DB::selectOne(
            'SELECT has_table_privilege(?, ?, ?) AS allowed',
            [$role, $relation, strtoupper($privilege)]
        );

        $this->assertSame(
            $expected,
            $this->toBool($row?->allowed ?? null),
            sprintf('Unexpected %s privilege for role %s on %s.', strtoupper($privilege), $role, $relation)
        );
    }

    private function expectRoleSelectToFail(string $role, string $relation): void
    {
        try {
            $this->runAsRole($role, static function () use ($relation): void {
                DB::select('SELECT * FROM '.$relation.' LIMIT 1');
            });

            $this->fail(sprintf('Expected SELECT to fail for role %s on %s.', $role, $relation));
        } catch (QueryException $exception) {
            $this->assertStringContainsString('permission denied', strtolower($exception->getMessage()));
        }
    }

    private function expectRoleInsertToFail(string $role, string $relation): void
    {
        try {
            $this->runAsRole($role, static function () use ($relation): void {
                DB::insert('INSERT INTO '.$relation." (id, payload) VALUES (20, 'app-write')");
            });

            $this->fail(sprintf('Expected INSERT to fail for role %s on %s.', $role, $relation));
        } catch (QueryException $exception) {
            $this->assertStringContainsString('permission denied', strtolower($exception->getMessage()));
        }
    }

    private function runAsRole(string $role, callable $callback): void
    {
        DB::statement('RESET ROLE');
        DB::statement('SET ROLE '.$this->quoteIdentifier($role));

        try {
            $callback();
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Projection schema authorization checks require PostgreSQL.');
        }
    }

    private function roleExists(string $role): bool
    {
        $row = DB::selectOne('SELECT EXISTS(SELECT 1 FROM pg_roles WHERE rolname = ?) AS role_exists', [$role]);

        return $this->toBool($row?->role_exists ?? null);
    }

    private function dropTransientProbeRelations(): void
    {
        DB::statement('DROP TABLE IF EXISTS public.run_attempts CASCADE');
        DB::statement('DROP TABLE IF EXISTS agent_projection.run_attempts CASCADE');
        DB::statement('DROP TABLE IF EXISTS agent_projection.projection_auth_probe CASCADE');
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quoteLiteral(string $literal): string
    {
        return "'".str_replace("'", "''", $literal)."'";
    }

    private function toBool(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }
}
