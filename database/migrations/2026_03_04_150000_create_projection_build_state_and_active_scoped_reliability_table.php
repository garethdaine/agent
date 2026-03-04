<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROJECTION_SCHEMA = 'agent_projection';

    /**
     * @var list<string>
     */
    private const PROJECTION_RELATIONS = [
        'run_attempts',
        'run_classifications',
        'workflow_reliability_windows',
        'workflow_reliability_current',
        'workflow_cost_rollups',
        'escalation_incidents',
        'escalation_alert_suppressions',
        'deployment_registrations',
        'workflow_gate_transitions',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->upPostgres();

            return;
        }

        $this->upGeneric();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->downPostgres();

            return;
        }

        $this->downGeneric();
    }

    private function upPostgres(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS '.self::PROJECTION_SCHEMA);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.telemetry_projection_builds (
                id UUID PRIMARY KEY,
                status VARCHAR(32) NOT NULL,
                activated_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS telemetry_projection_builds_status_idx ON agent_projection.telemetry_projection_builds (status)');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS telemetry_projection_builds_one_rebuilding_idx
            ON agent_projection.telemetry_projection_builds ((status))
            WHERE status = 'rebuilding'
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.telemetry_projection_build_state (
                id SMALLINT PRIMARY KEY CHECK (id = 1),
                active_projection_build_id UUID NULL REFERENCES agent_projection.telemetry_projection_builds(id) ON DELETE SET NULL,
                rebuilding_build_id UUID NULL REFERENCES agent_projection.telemetry_projection_builds(id) ON DELETE SET NULL,
                activated_at TIMESTAMPTZ NULL,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO agent_projection.telemetry_projection_build_state (id, active_projection_build_id, rebuilding_build_id, activated_at, updated_at)
            VALUES (1, NULL, NULL, NULL, NOW())
            ON CONFLICT (id) DO NOTHING
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.workflow_reliability_current (
                id BIGSERIAL PRIMARY KEY,
                workflow_key VARCHAR(160) NOT NULL,
                reliability_score NUMERIC(5, 2) NOT NULL DEFAULT 0,
                degraded_rate NUMERIC(5, 2) NULL,
                hard_fail_rate NUMERIC(5, 2) NULL,
                escalation_events INTEGER NULL,
                source_high_watermark_ingested_at TIMESTAMPTZ NULL,
                projection_build_id UUID NOT NULL REFERENCES agent_projection.telemetry_projection_builds(id) ON DELETE RESTRICT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT workflow_reliability_current_workflow_build_unique UNIQUE (workflow_key, projection_build_id)
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS workflow_reliability_current_workflow_idx ON agent_projection.workflow_reliability_current (workflow_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS workflow_reliability_current_build_idx ON agent_projection.workflow_reliability_current (projection_build_id)');

        foreach (self::PROJECTION_RELATIONS as $relation) {
            $location = $this->locateRelation($relation);
            if ($location === null) {
                continue;
            }

            [$schema, $table] = $location;
            if ($this->columnExists($schema, $table, 'projection_build_id')) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s.%s ADD COLUMN projection_build_id UUID NULL',
                $this->quoteIdentifier($schema),
                $this->quoteIdentifier($table)
            ));
        }
    }

    private function downPostgres(): void
    {
        DB::statement('DROP TABLE IF EXISTS agent_projection.workflow_reliability_current');

        foreach (self::PROJECTION_RELATIONS as $relation) {
            if ($relation === 'workflow_reliability_current') {
                continue;
            }

            $location = $this->locateRelation($relation);
            if ($location === null) {
                continue;
            }

            [$schema, $table] = $location;

            if (! $this->columnExists($schema, $table, 'projection_build_id')) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s.%s DROP COLUMN IF EXISTS projection_build_id',
                $this->quoteIdentifier($schema),
                $this->quoteIdentifier($table)
            ));
        }

        DB::statement('DROP TABLE IF EXISTS agent_projection.telemetry_projection_build_state');
        DB::statement('DROP TABLE IF EXISTS agent_projection.telemetry_projection_builds');
    }

    private function upGeneric(): void
    {
        if (! Schema::hasTable('telemetry_projection_builds')) {
            Schema::create('telemetry_projection_builds', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('status', 32);
                $table->timestampTz('activated_at')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('telemetry_projection_build_state')) {
            Schema::create('telemetry_projection_build_state', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->uuid('active_projection_build_id')->nullable();
                $table->uuid('rebuilding_build_id')->nullable();
                $table->timestampTz('activated_at')->nullable();
                $table->timestampTz('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('workflow_reliability_current')) {
            Schema::create('workflow_reliability_current', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_key', 160);
                $table->decimal('reliability_score', 5, 2)->default(0);
                $table->decimal('degraded_rate', 5, 2)->nullable();
                $table->decimal('hard_fail_rate', 5, 2)->nullable();
                $table->integer('escalation_events')->nullable();
                $table->timestampTz('source_high_watermark_ingested_at')->nullable();
                $table->uuid('projection_build_id')->nullable();
                $table->timestampsTz();
            });
        }
    }

    private function downGeneric(): void
    {
        Schema::dropIfExists('workflow_reliability_current');
        Schema::dropIfExists('telemetry_projection_build_state');
        Schema::dropIfExists('telemetry_projection_builds');
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function locateRelation(string $relation): ?array
    {
        foreach ([self::PROJECTION_SCHEMA, 'public'] as $schema) {
            $row = DB::selectOne(
                <<<'SQL'
                    SELECT 1
                    FROM pg_class c
                    INNER JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = ?
                      AND c.relname = ?
                      AND c.relkind IN ('r', 'p')
                    LIMIT 1
                SQL,
                [$schema, $relation]
            );

            if ($row !== null) {
                return [$schema, $relation];
            }
        }

        return null;
    }

    private function columnExists(string $schema, string $table, string $column): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = ?
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1
            SQL,
            [$schema, $table, $column]
        );

        return $row !== null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
