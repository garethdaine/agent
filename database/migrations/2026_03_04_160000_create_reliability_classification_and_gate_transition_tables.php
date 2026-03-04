<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROJECTION_SCHEMA = 'agent_projection';

    private const FAILURE_CLASS_ENUM = 'agent_telemetry_failure_class_enum';

    private const FAILURE_REASON_CODE_ENUM = 'agent_telemetry_failure_reason_code_enum';

    private const GATE_SOURCE_ENUM = 'agent_gate_transition_source_enum';

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
        $this->ensureTelemetryFailureEnumsExist();
        $this->ensureGateSourceEnumExists();

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.run_classifications (
                id BIGSERIAL PRIMARY KEY,
                run_id BIGINT NOT NULL REFERENCES public.agent_job_runs(id) ON DELETE CASCADE,
                workflow_key VARCHAR(160) NOT NULL,
                classification VARCHAR(32) NOT NULL,
                weight NUMERIC(4, 2) NOT NULL,
                hard_fail BOOLEAN NOT NULL DEFAULT FALSE,
                classification_reason_code VARCHAR(64) NULL,
                failure_class public.agent_telemetry_failure_class_enum NULL,
                failure_reason_code public.agent_telemetry_failure_reason_code_enum NULL,
                verification_completed_at TIMESTAMPTZ NULL,
                assisted_sla_expires_at TIMESTAMPTZ NULL,
                classified_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                reclassified_at TIMESTAMPTZ NULL,
                metadata_json JSONB NULL,
                projection_build_id UUID NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT run_classifications_run_unique UNIQUE (run_id)
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS run_classifications_workflow_idx ON agent_projection.run_classifications (workflow_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS run_classifications_classification_idx ON agent_projection.run_classifications (classification)');
        DB::statement('CREATE INDEX IF NOT EXISTS run_classifications_assisted_sla_idx ON agent_projection.run_classifications (assisted_sla_expires_at)');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.workflow_gate_transitions (
                id BIGSERIAL PRIMARY KEY,
                workflow_key VARCHAR(160) NOT NULL,
                previous_gate_state VARCHAR(32) NULL,
                new_gate_state VARCHAR(32) NOT NULL,
                source public.agent_gate_transition_source_enum NOT NULL,
                reason_code VARCHAR(64) NULL,
                reason TEXT NULL,
                actor_id VARCHAR(191) NULL,
                run_id VARCHAR(191) NULL,
                projection_build_id UUID NULL,
                metadata_json JSONB NULL,
                transitioned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS workflow_gate_transitions_workflow_idx ON agent_projection.workflow_gate_transitions (workflow_key)');
        DB::statement('CREATE INDEX IF NOT EXISTS workflow_gate_transitions_source_idx ON agent_projection.workflow_gate_transitions (source)');
        DB::statement('CREATE INDEX IF NOT EXISTS workflow_gate_transitions_transitioned_at_idx ON agent_projection.workflow_gate_transitions (transitioned_at)');
    }

    private function ensureTelemetryFailureEnumsExist(): void
    {
        DB::unprepared(sprintf(
            <<<'SQL'
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_type
                        WHERE typname = '%s'
                    ) THEN
                        BEGIN
                            CREATE TYPE public.%s AS ENUM (
                                'hard_fail',
                                'soft_fail',
                                'degraded',
                                'control_flow'
                            );
                        EXCEPTION
                            WHEN duplicate_object THEN NULL;
                        END;
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_type
                        WHERE typname = '%s'
                    ) THEN
                        BEGIN
                            CREATE TYPE public.%s AS ENUM (
                                'timeout',
                                'rate_limited',
                                'guardrail_blocked',
                                'approval_required',
                                'policy_blocked',
                                'dependency_error',
                                'infra_error',
                                'provider_error',
                                'validation_error',
                                'output_quality_fail',
                                'budget_breach',
                                'telemetry_unobservable',
                                'skipped',
                                'cancelled'
                            );
                        EXCEPTION
                            WHEN duplicate_object THEN NULL;
                        END;
                    END IF;
                END $$;
            SQL,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_REASON_CODE_ENUM,
            self::FAILURE_REASON_CODE_ENUM,
        ));
    }

    private function ensureGateSourceEnumExists(): void
    {
        DB::unprepared(sprintf(
            <<<'SQL'
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_type
                        WHERE typname = '%s'
                    ) THEN
                        BEGIN
                            CREATE TYPE public.%s AS ENUM (
                                'reliability_evaluation',
                                'manual_override',
                                'budget_enforcement',
                                'telemetry_outage',
                                'replay_recalculation'
                            );
                        EXCEPTION
                            WHEN duplicate_object THEN NULL;
                        END;
                    END IF;
                END $$;
            SQL,
            self::GATE_SOURCE_ENUM,
            self::GATE_SOURCE_ENUM,
        ));
    }

    private function downPostgres(): void
    {
        DB::statement('DROP TABLE IF EXISTS agent_projection.workflow_gate_transitions');
        DB::statement('DROP TABLE IF EXISTS agent_projection.run_classifications');
        DB::statement('DROP TYPE IF EXISTS public.'.self::GATE_SOURCE_ENUM);
    }

    private function upGeneric(): void
    {
        if (! Schema::hasTable('run_classifications')) {
            Schema::create('run_classifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->constrained('agent_job_runs')->cascadeOnDelete();
                $table->string('workflow_key', 160);
                $table->string('classification', 32);
                $table->decimal('weight', 4, 2);
                $table->boolean('hard_fail')->default(false);
                $table->string('classification_reason_code', 64)->nullable();
                $table->string('failure_class', 64)->nullable();
                $table->string('failure_reason_code', 64)->nullable();
                $table->timestampTz('verification_completed_at')->nullable();
                $table->timestampTz('assisted_sla_expires_at')->nullable();
                $table->timestampTz('classified_at')->nullable();
                $table->timestampTz('reclassified_at')->nullable();
                $table->json('metadata_json')->nullable();
                $table->uuid('projection_build_id')->nullable();
                $table->timestampsTz();

                $table->unique('run_id', 'run_classifications_run_unique');
                $table->index('workflow_key', 'run_classifications_workflow_idx');
                $table->index('classification', 'run_classifications_classification_idx');
                $table->index('assisted_sla_expires_at', 'run_classifications_assisted_sla_idx');
            });
        }

        if (! Schema::hasTable('workflow_gate_transitions')) {
            Schema::create('workflow_gate_transitions', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_key', 160);
                $table->string('previous_gate_state', 32)->nullable();
                $table->string('new_gate_state', 32);
                $table->string('source', 64);
                $table->string('reason_code', 64)->nullable();
                $table->text('reason')->nullable();
                $table->string('actor_id', 191)->nullable();
                $table->string('run_id', 191)->nullable();
                $table->uuid('projection_build_id')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestampTz('transitioned_at')->nullable();
                $table->timestampsTz();

                $table->index('workflow_key', 'workflow_gate_transitions_workflow_idx');
                $table->index('source', 'workflow_gate_transitions_source_idx');
                $table->index('transitioned_at', 'workflow_gate_transitions_transitioned_at_idx');
            });
        }
    }

    private function downGeneric(): void
    {
        Schema::dropIfExists('workflow_gate_transitions');
        Schema::dropIfExists('run_classifications');
    }
};
