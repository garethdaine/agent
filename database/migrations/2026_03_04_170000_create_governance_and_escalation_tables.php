<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROJECTION_SCHEMA = 'agent_projection';

    public function up(): void
    {
        $this->addGovernancePauseColumns();
        $this->createManualOverrideAuditTable();

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresIncidentAndSuppressionTables();

            return;
        }

        $this->createGenericIncidentAndSuppressionTables();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS '.self::PROJECTION_SCHEMA.'.escalation_alert_suppressions');
            DB::statement('DROP TABLE IF EXISTS '.self::PROJECTION_SCHEMA.'.escalation_incidents');
        } else {
            Schema::dropIfExists('escalation_alert_suppressions');
            Schema::dropIfExists('escalation_incidents');
        }

        Schema::dropIfExists('manual_override_audits');

        Schema::table('agent_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('agent_jobs', 'governance_paused_at')) {
                $table->dropColumn(['governance_paused_at', 'governance_pause_reason', 'governance_paused_by']);
            }
        });
    }

    private function addGovernancePauseColumns(): void
    {
        Schema::table('agent_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('agent_jobs', 'governance_paused_at')) {
                $table->timestampTz('governance_paused_at')->nullable()->after('is_enabled');
            }

            if (! Schema::hasColumn('agent_jobs', 'governance_pause_reason')) {
                $table->text('governance_pause_reason')->nullable()->after('governance_paused_at');
            }

            if (! Schema::hasColumn('agent_jobs', 'governance_paused_by')) {
                $table->string('governance_paused_by', 191)->nullable()->after('governance_pause_reason');
            }
        });
    }

    private function createManualOverrideAuditTable(): void
    {
        if (Schema::hasTable('manual_override_audits')) {
            return;
        }

        Schema::create('manual_override_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('workflow_key', 160);
            $table->string('actor_id', 191);
            $table->timestampTz('timestamp');
            $table->string('run_id', 191);
            $table->string('previous_state', 64);
            $table->string('new_state', 64);
            $table->text('reason');
            $table->string('action', 64);
            $table->boolean('authorized')->default(true);
            $table->json('metadata_json')->nullable();
            $table->timestampsTz();

            $table->index(['workflow_key', 'timestamp'], 'manual_override_audits_workflow_timestamp_idx');
            $table->index(['actor_id', 'timestamp'], 'manual_override_audits_actor_timestamp_idx');
        });
    }

    private function createPostgresIncidentAndSuppressionTables(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS '.self::PROJECTION_SCHEMA);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.escalation_incidents (
                id BIGSERIAL PRIMARY KEY,
                workflow_key VARCHAR(160) NOT NULL,
                trigger_type VARCHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL,
                reason_code VARCHAR(64) NULL,
                reason TEXT NULL,
                opened_at TIMESTAMPTZ NOT NULL,
                investigating_at TIMESTAMPTZ NULL,
                resolved_at TIMESTAMPTZ NULL,
                last_triggered_at TIMESTAMPTZ NOT NULL,
                projection_build_id UUID NULL,
                metadata_json JSONB NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS escalation_incidents_workflow_trigger_idx ON agent_projection.escalation_incidents (workflow_key, trigger_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS escalation_incidents_status_idx ON agent_projection.escalation_incidents (status)');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS escalation_incidents_unresolved_unique
            ON agent_projection.escalation_incidents (workflow_key, trigger_type)
            WHERE status IN ('open', 'investigating')
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.escalation_alert_suppressions (
                id BIGSERIAL PRIMARY KEY,
                workflow_key VARCHAR(160) NOT NULL,
                trigger_type VARCHAR(64) NOT NULL,
                suppression_date_utc DATE NOT NULL,
                incident_id BIGINT NULL REFERENCES agent_projection.escalation_incidents(id) ON DELETE SET NULL,
                metadata_json JSONB NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS escalation_alert_suppressions_daily_unique ON agent_projection.escalation_alert_suppressions (workflow_key, trigger_type, suppression_date_utc)');
        DB::statement('CREATE INDEX IF NOT EXISTS escalation_alert_suppressions_incident_idx ON agent_projection.escalation_alert_suppressions (incident_id)');
    }

    private function createGenericIncidentAndSuppressionTables(): void
    {
        if (! Schema::hasTable('escalation_incidents')) {
            Schema::create('escalation_incidents', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_key', 160);
                $table->string('trigger_type', 64);
                $table->string('status', 32);
                $table->string('reason_code', 64)->nullable();
                $table->text('reason')->nullable();
                $table->timestampTz('opened_at');
                $table->timestampTz('investigating_at')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->timestampTz('last_triggered_at');
                $table->uuid('projection_build_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestampsTz();

                $table->index(['workflow_key', 'trigger_type'], 'escalation_incidents_workflow_trigger_idx');
                $table->index(['status'], 'escalation_incidents_status_idx');
            });
        }

        if (! Schema::hasTable('escalation_alert_suppressions')) {
            Schema::create('escalation_alert_suppressions', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_key', 160);
                $table->string('trigger_type', 64);
                $table->date('suppression_date_utc');
                $table->foreignId('incident_id')->nullable()->constrained('escalation_incidents')->nullOnDelete();
                $table->json('metadata_json')->nullable();
                $table->timestampsTz();

                $table->unique(['workflow_key', 'trigger_type', 'suppression_date_utc'], 'escalation_alert_suppressions_daily_unique');
            });
        }
    }
};
