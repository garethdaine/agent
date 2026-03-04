<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'telemetry_event_ledger';

    private const FAILURE_CLASS_ENUM = 'agent_telemetry_failure_class_enum';

    private const FAILURE_REASON_CODE_ENUM = 'agent_telemetry_failure_reason_code_enum';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->ensureTelemetryEnumsExist();
        $this->addMissingColumns();
        $this->castFailureColumnsToEnums();
        $this->ensureIndexes();
    }

    public function down(): void
    {
        // No-op: reconciliation migration intentionally avoids destructive rollbacks.
    }

    private function ensureTelemetryEnumsExist(): void
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

    private function addMissingColumns(): void
    {
        DB::statement(sprintf(
            <<<'SQL'
                ALTER TABLE public.%s
                    ADD COLUMN IF NOT EXISTS schema_name VARCHAR(191) NOT NULL DEFAULT 'agent.telemetry.event',
                    ADD COLUMN IF NOT EXISTS schema_version VARCHAR(64) NOT NULL DEFAULT '1.0.0',
                    ADD COLUMN IF NOT EXISTS run_id VARCHAR(191) NULL,
                    ADD COLUMN IF NOT EXISTS parent_run_id VARCHAR(191) NULL,
                    ADD COLUMN IF NOT EXISTS event_sequence BIGINT NOT NULL DEFAULT 0,
                    ADD COLUMN IF NOT EXISTS event_at TIMESTAMPTZ NULL,
                    ADD COLUMN IF NOT EXISTS schema_hash VARCHAR(128) NULL,
                    ADD COLUMN IF NOT EXISTS normalizer_version VARCHAR(128) NULL,
                    ADD COLUMN IF NOT EXISTS registry_revision VARCHAR(128) NULL,
                    ADD COLUMN IF NOT EXISTS rate_card_version VARCHAR(64) NULL,
                    ADD COLUMN IF NOT EXISTS failure_class public.%s NULL,
                    ADD COLUMN IF NOT EXISTS failure_reason_code public.%s NULL,
                    ADD COLUMN IF NOT EXISTS telemetry_estimated BOOLEAN NOT NULL DEFAULT FALSE,
                    ADD COLUMN IF NOT EXISTS telemetry_delayed BOOLEAN NOT NULL DEFAULT FALSE,
                    ADD COLUMN IF NOT EXISTS terminal BOOLEAN NOT NULL DEFAULT FALSE
            SQL,
            self::TABLE,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_REASON_CODE_ENUM,
        ));
    }

    private function castFailureColumnsToEnums(): void
    {
        DB::statement(sprintf(
            <<<'SQL'
                ALTER TABLE public.%s
                ALTER COLUMN failure_class TYPE public.%s
                USING (
                    CASE
                        WHEN failure_class IS NULL OR btrim(failure_class::text) = '' THEN NULL
                        WHEN failure_class::text IN ('hard_fail', 'soft_fail', 'degraded', 'control_flow')
                            THEN failure_class::text::public.%s
                        ELSE NULL
                    END
                ),
                ALTER COLUMN failure_reason_code TYPE public.%s
                USING (
                    CASE
                        WHEN failure_reason_code IS NULL OR btrim(failure_reason_code::text) = '' THEN NULL
                        WHEN failure_reason_code::text IN (
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
                        ) THEN failure_reason_code::text::public.%s
                        ELSE NULL
                    END
                )
            SQL,
            self::TABLE,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_REASON_CODE_ENUM,
            self::FAILURE_REASON_CODE_ENUM,
        ));
    }

    private function ensureIndexes(): void
    {
        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS telemetry_event_ledger_run_idx ON public.%s (run_id)',
            self::TABLE
        ));
        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS telemetry_event_ledger_attempt_idx ON public.%s (run_attempt_id)',
            self::TABLE
        ));
    }
};
