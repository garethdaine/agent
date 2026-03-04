<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROJECTION_SCHEMA = 'agent_projection';

    /**
     * @var list<string>
     */
    private const KNOWN_PROJECTION_RELATIONS = [
        'run_attempts',
        'run_classifications',
        'workflow_reliability_windows',
        'workflow_reliability_current',
        'workflow_cost_rollups',
        'escalation_incidents',
        'escalation_alert_suppressions',
        'deployment_registrations',
        'workflow_gate_transitions',
        'telemetry_projection_builds',
        'telemetry_projection_build_state',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf('CREATE SCHEMA IF NOT EXISTS %s', $this->quoteIdentifier(self::PROJECTION_SCHEMA)));

        $this->moveKnownProjectionRelationsToDedicatedSchema();
        $this->hardenProjectionSchemaPrivileges();
        $this->assertReportingRolesCannotReadOrMutateProjectionRelations();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::KNOWN_PROJECTION_RELATIONS as $relationName) {
            $relationKind = $this->relationKind(self::PROJECTION_SCHEMA, $relationName);
            if ($relationKind === null || $this->relationKind('public', $relationName) !== null) {
                continue;
            }

            $this->moveRelationToSchema(self::PROJECTION_SCHEMA, 'public', $relationName, $relationKind);
        }
    }

    private function moveKnownProjectionRelationsToDedicatedSchema(): void
    {
        foreach (self::KNOWN_PROJECTION_RELATIONS as $relationName) {
            $relationKind = $this->relationKind('public', $relationName);
            if ($relationKind === null) {
                continue;
            }

            if ($this->relationKind(self::PROJECTION_SCHEMA, $relationName) !== null) {
                throw new RuntimeException(sprintf(
                    'Projection relation conflict: both public.%s and %s.%s exist.',
                    $relationName,
                    self::PROJECTION_SCHEMA,
                    $relationName
                ));
            }

            $this->moveRelationToSchema('public', self::PROJECTION_SCHEMA, $relationName, $relationKind);
        }
    }

    private function hardenProjectionSchemaPrivileges(): void
    {
        DB::statement(sprintf('REVOKE ALL ON SCHEMA %s FROM PUBLIC', $this->quoteIdentifier(self::PROJECTION_SCHEMA)));
        DB::statement(sprintf('REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA %s FROM PUBLIC', $this->quoteIdentifier(self::PROJECTION_SCHEMA)));
        DB::statement(sprintf('REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA %s FROM PUBLIC', $this->quoteIdentifier(self::PROJECTION_SCHEMA)));

        foreach ($this->reportingRoles() as $role) {
            if (! $this->roleExists($role)) {
                continue;
            }

            $quotedRole = $this->quoteIdentifier($role);
            $quotedSchema = $this->quoteIdentifier(self::PROJECTION_SCHEMA);

            DB::statement(sprintf('REVOKE USAGE ON SCHEMA %s FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('REVOKE SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON ALL TABLES IN SCHEMA %s FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('REVOKE USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA %s FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s REVOKE SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON TABLES FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s REVOKE USAGE, SELECT, UPDATE ON SEQUENCES FROM %s', $quotedSchema, $quotedRole));
        }

        foreach ($this->applicationRoles() as $role) {
            if (! $this->roleExists($role)) {
                continue;
            }

            $quotedRole = $this->quoteIdentifier($role);
            $quotedSchema = $this->quoteIdentifier(self::PROJECTION_SCHEMA);

            DB::statement(sprintf('GRANT USAGE ON SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('REVOKE INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON ALL TABLES IN SCHEMA %s FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('GRANT SELECT ON ALL TABLES IN SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s REVOKE INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON TABLES FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s GRANT SELECT ON TABLES TO %s', $quotedSchema, $quotedRole));
        }

        foreach ($this->projectorRoles() as $role) {
            if (! $this->roleExists($role)) {
                continue;
            }

            $quotedRole = $this->quoteIdentifier($role);
            $quotedSchema = $this->quoteIdentifier(self::PROJECTION_SCHEMA);

            DB::statement(sprintf('GRANT USAGE ON SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('REVOKE DELETE, TRUNCATE, REFERENCES, TRIGGER ON ALL TABLES IN SCHEMA %s FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('GRANT USAGE, UPDATE ON ALL SEQUENCES IN SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s REVOKE DELETE, TRUNCATE, REFERENCES, TRIGGER ON TABLES FROM %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s GRANT SELECT, INSERT, UPDATE ON TABLES TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s GRANT USAGE, UPDATE ON SEQUENCES TO %s', $quotedSchema, $quotedRole));
        }

        foreach ($this->adminRoles() as $role) {
            if (! $this->roleExists($role)) {
                continue;
            }

            $quotedRole = $this->quoteIdentifier($role);
            $quotedSchema = $this->quoteIdentifier(self::PROJECTION_SCHEMA);

            DB::statement(sprintf('GRANT USAGE, CREATE ON SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA %s TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s GRANT ALL PRIVILEGES ON TABLES TO %s', $quotedSchema, $quotedRole));
            DB::statement(sprintf('ALTER DEFAULT PRIVILEGES IN SCHEMA %s GRANT ALL PRIVILEGES ON SEQUENCES TO %s', $quotedSchema, $quotedRole));
        }
    }

    private function assertReportingRolesCannotReadOrMutateProjectionRelations(): void
    {
        $relations = DB::select(
            <<<'SQL'
                SELECT c.relname
                FROM pg_class c
                INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ?
                  AND c.relkind IN ('r', 'p', 'v', 'm')
                ORDER BY c.relname ASC
            SQL,
            [self::PROJECTION_SCHEMA]
        );

        $projectionRelations = array_map(
            static fn (object $row): string => (string) $row->relname,
            $relations
        );

        if ($projectionRelations === []) {
            return;
        }

        foreach ($this->reportingRoles() as $role) {
            if (! $this->roleExists($role)) {
                continue;
            }

            foreach ($projectionRelations as $relationName) {
                $qualifiedRelation = self::PROJECTION_SCHEMA.'.'.$relationName;

                foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                    $row = DB::selectOne(
                        'SELECT has_table_privilege(?, ?, ?) AS has_privilege',
                        [$role, $qualifiedRelation, $privilege]
                    );

                    if ($this->toBool($row->has_privilege ?? null)) {
                        throw new RuntimeException(sprintf(
                            'Projection permission verification failed: role %s unexpectedly has %s on %s.',
                            $role,
                            $privilege,
                            $qualifiedRelation
                        ));
                    }
                }
            }
        }
    }

    private function moveRelationToSchema(string $sourceSchema, string $targetSchema, string $relationName, string $relationKind): void
    {
        $qualifiedSource = $this->quoteIdentifier($sourceSchema).'.'.$this->quoteIdentifier($relationName);
        $quotedTargetSchema = $this->quoteIdentifier($targetSchema);

        match ($relationKind) {
            'v' => DB::statement(sprintf('ALTER VIEW %s SET SCHEMA %s', $qualifiedSource, $quotedTargetSchema)),
            'm' => DB::statement(sprintf('ALTER MATERIALIZED VIEW %s SET SCHEMA %s', $qualifiedSource, $quotedTargetSchema)),
            default => DB::statement(sprintf('ALTER TABLE %s SET SCHEMA %s', $qualifiedSource, $quotedTargetSchema)),
        };
    }

    private function relationKind(string $schema, string $relationName): ?string
    {
        $row = DB::selectOne(
            <<<'SQL'
                SELECT c.relkind
                FROM pg_class c
                INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ?
                  AND c.relname = ?
                  AND c.relkind IN ('r', 'p', 'v', 'm')
                LIMIT 1
            SQL,
            [$schema, $relationName]
        );

        if (! $row || ! isset($row->relkind)) {
            return null;
        }

        return (string) $row->relkind;
    }

    /**
     * @return list<string>
     */
    private function reportingRoles(): array
    {
        $roles = array_merge(
            $this->parseCsvRoles((string) env('AGENT_DB_REPORTING_ROLES', '')),
            $this->parseCsvRoles((string) env('AGENT_DB_ANALYTICS_ROLES', '')),
            $this->singleRole(env('AGENT_DB_ANALYTICS_ROLE')),
            ['agent_reporting', 'agent_analytics']
        );

        return array_values(array_unique(array_filter($roles, static fn (string $role): bool => $role !== '')));
    }

    /**
     * @return list<string>
     */
    private function applicationRoles(): array
    {
        $roles = array_merge(
            $this->singleRole(env('AGENT_DB_APP_ROLE')),
            $this->parseCsvRoles((string) env('AGENT_DB_APP_ROLES', ''))
        );

        return array_values(array_unique(array_filter($roles, static fn (string $role): bool => $role !== '')));
    }

    /**
     * @return list<string>
     */
    private function projectorRoles(): array
    {
        $roles = array_merge(
            $this->singleRole(env('AGENT_DB_PROJECTOR_ROLE')),
            $this->parseCsvRoles((string) env('AGENT_DB_PROJECTOR_ROLES', ''))
        );

        return array_values(array_unique(array_filter($roles, static fn (string $role): bool => $role !== '')));
    }

    /**
     * @return list<string>
     */
    private function adminRoles(): array
    {
        $configuredConnectionUsername = config('database.connections.'.config('database.default').'.username');

        $roles = array_merge(
            $this->singleRole(env('AGENT_DB_ADMIN_ROLE')),
            $this->singleRole(env('AGENT_DB_MIGRATION_ROLE')),
            $this->singleRole($configuredConnectionUsername),
            $this->singleRole(env('TEST_DB_USERNAME')),
            $this->singleRole(env('DB_USERNAME'))
        );

        return array_values(array_unique(array_filter($roles, static fn (string $role): bool => $role !== '')));
    }

    /**
     * @return list<string>
     */
    private function singleRole(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        $role = trim($value);

        return $role !== '' ? [$role] : [];
    }

    /**
     * @return list<string>
     */
    private function parseCsvRoles(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            str_getcsv($csv)
        ), static fn (string $value): bool => $value !== ''));
    }

    private function roleExists(string $role): bool
    {
        $row = DB::selectOne('SELECT EXISTS(SELECT 1 FROM pg_roles WHERE rolname = ?) AS role_exists', [$role]);

        return $this->toBool($row->role_exists ?? null);
    }

    private function toBool(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
