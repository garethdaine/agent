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
        $this->createWorkflowMonthlyBudgetPoliciesTable();

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresProjectionTables();

            return;
        }

        $this->createGenericProjectionTables();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS '.self::PROJECTION_SCHEMA.'.workflow_budget_events');
            DB::statement('DROP TABLE IF EXISTS '.self::PROJECTION_SCHEMA.'.workflow_cost_rollups');
        } else {
            Schema::dropIfExists('workflow_budget_events');
            Schema::dropIfExists('workflow_cost_rollups');
        }

        Schema::dropIfExists('workflow_monthly_budget_policies');
    }

    private function createWorkflowMonthlyBudgetPoliciesTable(): void
    {
        if (Schema::hasTable('workflow_monthly_budget_policies')) {
            return;
        }

        Schema::create('workflow_monthly_budget_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('workflow_key', 160)->unique();
            $table->decimal('monthly_budget_usd', 14, 6);
            $table->decimal('warning_threshold_percent', 5, 2)->default((float) config('agent.cost_governance.default_warning_threshold_percent', 80.0));
            $table->decimal('enforcement_threshold_percent', 5, 2)->default((float) config('agent.cost_governance.default_enforcement_threshold_percent', 100.0));
            $table->boolean('is_active')->default(true);
            $table->json('metadata_json')->nullable();
            $table->timestampsTz();

            $table->index(['workflow_key', 'is_active'], 'workflow_monthly_budget_policies_workflow_active_idx');
        });
    }

    private function createPostgresProjectionTables(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS '.self::PROJECTION_SCHEMA);

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.workflow_cost_rollups (
                id BIGSERIAL PRIMARY KEY,
                workflow_key VARCHAR(160) NOT NULL,
                run_id VARCHAR(191) NOT NULL,
                budget_month_utc CHAR(7) NOT NULL,
                rate_card_version VARCHAR(64) NOT NULL,
                model VARCHAR(191) NOT NULL,
                input_tokens BIGINT NOT NULL DEFAULT 0,
                output_tokens BIGINT NOT NULL DEFAULT 0,
                canonical_cost_usd NUMERIC(14, 6) NOT NULL,
                provider_billed_cost_usd NUMERIC(14, 6) NULL,
                projection_build_id UUID NULL,
                occurred_at TIMESTAMPTZ NOT NULL,
                metadata_json JSONB NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT workflow_cost_rollups_workflow_run_unique UNIQUE (workflow_key, run_id)
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS workflow_cost_rollups_workflow_month_idx ON agent_projection.workflow_cost_rollups (workflow_key, budget_month_utc)');
        DB::statement('CREATE INDEX IF NOT EXISTS workflow_cost_rollups_rate_card_idx ON agent_projection.workflow_cost_rollups (rate_card_version)');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agent_projection.workflow_budget_events (
                id BIGSERIAL PRIMARY KEY,
                workflow_key VARCHAR(160) NOT NULL,
                budget_month_utc CHAR(7) NOT NULL,
                event_type VARCHAR(32) NOT NULL,
                run_id VARCHAR(191) NULL,
                triggered_at TIMESTAMPTZ NOT NULL,
                policy_snapshot_json JSONB NOT NULL,
                utilization_percent NUMERIC(6, 2) NOT NULL,
                canonical_monthly_spend_usd NUMERIC(14, 6) NOT NULL,
                provider_monthly_spend_usd NUMERIC(14, 6) NOT NULL,
                projection_build_id UUID NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT workflow_budget_events_unique_per_run UNIQUE (workflow_key, budget_month_utc, event_type, run_id)
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS workflow_budget_events_workflow_month_idx ON agent_projection.workflow_budget_events (workflow_key, budget_month_utc)');
        DB::statement('CREATE INDEX IF NOT EXISTS workflow_budget_events_type_idx ON agent_projection.workflow_budget_events (event_type)');
    }

    private function createGenericProjectionTables(): void
    {
        if (! Schema::hasTable('workflow_cost_rollups')) {
            Schema::create('workflow_cost_rollups', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_key', 160);
                $table->string('run_id', 191);
                $table->char('budget_month_utc', 7);
                $table->string('rate_card_version', 64);
                $table->string('model', 191);
                $table->unsignedBigInteger('input_tokens')->default(0);
                $table->unsignedBigInteger('output_tokens')->default(0);
                $table->decimal('canonical_cost_usd', 14, 6);
                $table->decimal('provider_billed_cost_usd', 14, 6)->nullable();
                $table->uuid('projection_build_id')->nullable();
                $table->timestampTz('occurred_at');
                $table->json('metadata_json')->nullable();
                $table->timestampsTz();

                $table->unique(['workflow_key', 'run_id'], 'workflow_cost_rollups_workflow_run_unique');
                $table->index(['workflow_key', 'budget_month_utc'], 'workflow_cost_rollups_workflow_month_idx');
                $table->index(['rate_card_version'], 'workflow_cost_rollups_rate_card_idx');
            });
        }

        if (! Schema::hasTable('workflow_budget_events')) {
            Schema::create('workflow_budget_events', function (Blueprint $table): void {
                $table->id();
                $table->string('workflow_key', 160);
                $table->char('budget_month_utc', 7);
                $table->string('event_type', 32);
                $table->string('run_id', 191)->nullable();
                $table->timestampTz('triggered_at');
                $table->json('policy_snapshot_json');
                $table->decimal('utilization_percent', 6, 2);
                $table->decimal('canonical_monthly_spend_usd', 14, 6);
                $table->decimal('provider_monthly_spend_usd', 14, 6);
                $table->uuid('projection_build_id')->nullable();
                $table->timestampsTz();

                $table->unique(
                    ['workflow_key', 'budget_month_utc', 'event_type', 'run_id'],
                    'workflow_budget_events_unique_per_run'
                );
                $table->index(['workflow_key', 'budget_month_utc'], 'workflow_budget_events_workflow_month_idx');
                $table->index(['event_type'], 'workflow_budget_events_type_idx');
            });
        }
    }
};
