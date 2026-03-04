<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEDGER_TABLE = 'telemetry_event_ledger';

    private const MUTATION_AUDIT_TABLE = 'telemetry_ledger_mutation_attempts';

    private const MUTATION_GUARD_FUNCTION = 'telemetry_event_ledger_block_mutations';

    private const MUTATION_GUARD_TRIGGER = 'telemetry_event_ledger_block_mutations_trigger';

    private const FAILURE_CLASS_ENUM = 'agent_telemetry_failure_class_enum';

    private const FAILURE_REASON_CODE_ENUM = 'agent_telemetry_failure_reason_code_enum';

    public function up(): void
    {
        if (! Schema::hasTable(self::LEDGER_TABLE)) {
            Schema::create(self::LEDGER_TABLE, function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('schema_name', 191)->default('agent.telemetry.event');
                $table->string('schema_version', 64)->default('1.0.0');
                $table->string('event_id', 191);
                $table->string('run_id', 191)->nullable();
                $table->string('run_attempt_id', 191);
                $table->string('parent_run_id', 191)->nullable();
                $table->string('workflow_key', 160)->nullable();
                $table->string('event_type', 160);
                $table->unsignedBigInteger('event_sequence')->default(0);
                $table->timestampTz('event_at')->nullable();
                $table->jsonb('payload_json');
                $table->string('schema_hash', 128)->nullable();
                $table->string('normalizer_version', 128)->nullable();
                $table->string('registry_revision', 128)->nullable();
                $table->string('rate_card_version', 64)->nullable();
                $table->string('failure_class', 64)->nullable();
                $table->string('failure_reason_code', 64)->nullable();
                $table->boolean('telemetry_estimated')->default(false);
                $table->boolean('telemetry_delayed')->default(false);
                $table->boolean('terminal')->default(false);
                $table->timestampTz('ingested_at');
                $table->timestampTz('created_at')->useCurrent();

                $table->unique(['event_id', 'run_attempt_id'], 'telemetry_event_ledger_event_attempt_unique');
                $table->index(['workflow_key'], 'telemetry_event_ledger_workflow_idx');
                $table->index(['run_id'], 'telemetry_event_ledger_run_idx');
                $table->index(['run_attempt_id'], 'telemetry_event_ledger_attempt_idx');
                $table->index(['ingested_at'], 'telemetry_event_ledger_ingested_idx');
            });
        }

        if (! Schema::hasTable(self::MUTATION_AUDIT_TABLE)) {
            Schema::create(self::MUTATION_AUDIT_TABLE, function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->timestamp('attempted_at')->default(DB::raw("(now() AT TIME ZONE 'UTC')"));
                $table->string('db_user', 255);
                $table->string('application_name', 255)->nullable();
                $table->string('client_addr', 64)->nullable();
                $table->string('operation', 16);
                $table->string('table_name', 255);
                $table->text('row_identifier')->nullable();
                $table->text('query_text')->nullable();
                $table->jsonb('context_json')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['table_name', 'operation'], 'telemetry_ledger_mutation_attempts_table_op_idx');
                $table->index(['attempted_at'], 'telemetry_ledger_mutation_attempts_attempted_idx');
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->createTelemetryEnums();
        $this->castFailureColumnsToPgEnums();
        $this->enableDblinkWhenAvailable();

        DB::statement('REVOKE UPDATE, DELETE ON TABLE public.telemetry_event_ledger FROM PUBLIC');

        foreach ($this->mutationRestrictedRoles() as $role) {
            if (! $this->roleExists($role)) {
                continue;
            }

            $quotedRole = $this->quoteIdentifier($role);

            DB::statement(sprintf('REVOKE UPDATE, DELETE ON TABLE public.telemetry_event_ledger FROM %s', $quotedRole));
            DB::statement(sprintf('GRANT SELECT, INSERT ON TABLE public.telemetry_event_ledger TO %s', $quotedRole));
        }

        DB::statement('ALTER TABLE public.telemetry_ledger_mutation_attempts DROP CONSTRAINT IF EXISTS telemetry_ledger_mutation_attempts_operation_check');
        DB::statement("ALTER TABLE public.telemetry_ledger_mutation_attempts ADD CONSTRAINT telemetry_ledger_mutation_attempts_operation_check CHECK (operation IN ('UPDATE', 'DELETE'))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.telemetry_event_ledger_block_mutations()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            DECLARE
                v_row_identifier text;
                v_query text;
                v_context jsonb;
                v_audit_sql text;
                v_conn_str text;
                v_has_dblink boolean;
            BEGIN
                v_row_identifier := COALESCE(to_jsonb(OLD)->>'id', to_jsonb(OLD)->>'event_id');

                BEGIN
                    SELECT query INTO v_query
                    FROM pg_stat_activity
                    WHERE pid = pg_backend_pid();
                EXCEPTION WHEN OTHERS THEN
                    v_query := NULL;
                END;

                v_context := jsonb_build_object(
                    'trigger_name', TG_NAME,
                    'schema_name', TG_TABLE_SCHEMA,
                    'table_name', TG_TABLE_NAME,
                    'operation', TG_OP,
                    'pid', pg_backend_pid(),
                    'txid', txid_current()
                );

                SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'dblink') INTO v_has_dblink;

                IF v_has_dblink THEN
                    v_conn_str := format(
                        'host=%s port=%s dbname=%s user=%s',
                        split_part(COALESCE(inet_server_addr()::text, '127.0.0.1'), '/', 1),
                        inet_server_port(),
                        current_database(),
                        current_user
                    );

                    v_audit_sql := 'INSERT INTO public.telemetry_ledger_mutation_attempts '
                        || '(attempted_at, db_user, application_name, client_addr, operation, table_name, row_identifier, query_text, context_json, created_at) VALUES ('
                        || quote_literal((now() AT TIME ZONE 'UTC')) || ', '
                        || quote_literal(current_user) || ', '
                        || quote_nullable(current_setting('application_name', true)) || ', '
                        || quote_nullable(inet_client_addr()::text) || ', '
                        || quote_literal(TG_OP) || ', '
                        || quote_literal(TG_TABLE_NAME) || ', '
                        || quote_nullable(v_row_identifier) || ', '
                        || quote_nullable(v_query) || ', '
                        || quote_nullable(v_context::text) || '::jsonb, '
                        || quote_literal(now()) || ')';

                    BEGIN
                        PERFORM dblink_exec(v_conn_str, v_audit_sql);
                    EXCEPTION WHEN OTHERS THEN
                        INSERT INTO public.telemetry_ledger_mutation_attempts (
                            attempted_at,
                            db_user,
                            application_name,
                            client_addr,
                            operation,
                            table_name,
                            row_identifier,
                            query_text,
                            context_json,
                            created_at
                        ) VALUES (
                            (now() AT TIME ZONE 'UTC'),
                            current_user,
                            current_setting('application_name', true),
                            inet_client_addr()::text,
                            TG_OP,
                            TG_TABLE_NAME,
                            v_row_identifier,
                            v_query,
                            v_context,
                            now()
                        );
                    END;
                ELSE
                    INSERT INTO public.telemetry_ledger_mutation_attempts (
                        attempted_at,
                        db_user,
                        application_name,
                        client_addr,
                        operation,
                        table_name,
                        row_identifier,
                        query_text,
                        context_json,
                        created_at
                    ) VALUES (
                        (now() AT TIME ZONE 'UTC'),
                        current_user,
                        current_setting('application_name', true),
                        inet_client_addr()::text,
                        TG_OP,
                        TG_TABLE_NAME,
                        v_row_identifier,
                        v_query,
                        v_context,
                        now()
                    );
                END IF;

                RAISE EXCEPTION 'telemetry_event_ledger is append-only; UPDATE and DELETE are prohibited.'
                    USING ERRCODE = 'P0001',
                          DETAIL = format('Blocked %s on %I.%I row_identifier=%s', TG_OP, TG_TABLE_SCHEMA, TG_TABLE_NAME, COALESCE(v_row_identifier, 'unknown'));
            END;
            $function$;
        SQL);

        DB::unprepared(sprintf(
            'DROP TRIGGER IF EXISTS %s ON public.%s',
            self::MUTATION_GUARD_TRIGGER,
            self::LEDGER_TABLE
        ));

        DB::unprepared(sprintf(
            'CREATE TRIGGER %s BEFORE UPDATE OR DELETE ON public.%s FOR EACH ROW EXECUTE FUNCTION public.%s() ',
            self::MUTATION_GUARD_TRIGGER,
            self::LEDGER_TABLE,
            self::MUTATION_GUARD_FUNCTION
        ));

        DB::statement("COMMENT ON TABLE public.telemetry_event_ledger IS 'telemetry_event_ledger is append-only. UPDATE and DELETE are prohibited and enforced via database trigger.'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable(self::LEDGER_TABLE)) {
            DB::unprepared(sprintf(
                'DROP TRIGGER IF EXISTS %s ON public.%s',
                self::MUTATION_GUARD_TRIGGER,
                self::LEDGER_TABLE
            ));
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(sprintf(
                'DROP FUNCTION IF EXISTS public.%s()',
                self::MUTATION_GUARD_FUNCTION
            ));
        }

        Schema::dropIfExists(self::MUTATION_AUDIT_TABLE);
        Schema::dropIfExists(self::LEDGER_TABLE);

        if (DB::getDriverName() === 'pgsql') {
            $this->dropTelemetryEnums();
        }
    }

    private function createTelemetryEnums(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_type
                    WHERE typname = 'agent_telemetry_failure_class_enum'
                ) THEN
                    CREATE TYPE public.agent_telemetry_failure_class_enum AS ENUM (
                        'hard_fail',
                        'soft_fail',
                        'degraded',
                        'control_flow'
                    );
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_type
                    WHERE typname = 'agent_telemetry_failure_reason_code_enum'
                ) THEN
                    CREATE TYPE public.agent_telemetry_failure_reason_code_enum AS ENUM (
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
                END IF;
            END $$;
        SQL);
    }

    private function castFailureColumnsToPgEnums(): void
    {
        DB::unprepared(sprintf(
            <<<'SQL'
                ALTER TABLE public.%s
                ALTER COLUMN failure_class TYPE public.%s
                USING (
                    CASE
                        WHEN failure_class IS NULL OR btrim(failure_class) = '' THEN NULL
                        ELSE failure_class::public.%s
                    END
                ),
                ALTER COLUMN failure_reason_code TYPE public.%s
                USING (
                    CASE
                        WHEN failure_reason_code IS NULL OR btrim(failure_reason_code) = '' THEN NULL
                        ELSE failure_reason_code::public.%s
                    END
                )
            SQL,
            self::LEDGER_TABLE,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_CLASS_ENUM,
            self::FAILURE_REASON_CODE_ENUM,
            self::FAILURE_REASON_CODE_ENUM
        ));
    }

    private function dropTelemetryEnums(): void
    {
        DB::statement(sprintf(
            'DROP TYPE IF EXISTS public.%s',
            self::FAILURE_REASON_CODE_ENUM
        ));
        DB::statement(sprintf(
            'DROP TYPE IF EXISTS public.%s',
            self::FAILURE_CLASS_ENUM
        ));
    }

    private function enableDblinkWhenAvailable(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS dblink');
        } catch (\Throwable) {
            // Fall back to in-transaction insert when extension creation is not allowed.
        }
    }

    /**
     * @return list<string>
     */
    private function mutationRestrictedRoles(): array
    {
        $roles = [];

        $configuredConnectionUsername = config('database.connections.'.config('database.default').'.username');
        if (is_string($configuredConnectionUsername) && trim($configuredConnectionUsername) !== '') {
            $roles[] = trim($configuredConnectionUsername);
        }

        foreach (['AGENT_DB_APP_ROLE', 'TEST_DB_USERNAME', 'DB_USERNAME'] as $envKey) {
            $value = env($envKey);
            if (is_string($value) && trim($value) !== '') {
                $roles[] = trim($value);
            }
        }

        $nonAdminRoles = env('AGENT_DB_NON_ADMIN_ROLES', '');
        if (is_string($nonAdminRoles) && trim($nonAdminRoles) !== '') {
            foreach (str_getcsv($nonAdminRoles) as $role) {
                $role = trim((string) $role);
                if ($role !== '') {
                    $roles[] = $role;
                }
            }
        }

        return array_values(array_unique($roles));
    }

    private function roleExists(string $role): bool
    {
        $row = DB::selectOne('SELECT EXISTS(SELECT 1 FROM pg_roles WHERE rolname = ?) AS role_exists', [$role]);

        $value = $row->role_exists ?? null;

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
