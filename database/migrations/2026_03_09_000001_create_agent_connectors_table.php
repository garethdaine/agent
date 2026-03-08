<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 64)->unique();
            $table->string('display_name', 128);
            $table->text('description');
            $table->string('category', 64);
            $table->jsonb('industries')->default('[]');
            $table->string('version', 20);
            $table->string('auth_type', 30);
            $table->jsonb('auth_config')->nullable();
            $table->text('base_url')->nullable();
            $table->jsonb('rate_limits')->nullable();
            $table->string('cost_model', 20)->default('free');
            $table->string('risk_level', 20)->default('standard');
            $table->jsonb('actions')->nullable();
            $table->jsonb('webhooks')->default('[]');
            $table->text('icon_path')->nullable();
            $table->string('mcp_tool_prefix', 64)->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->index('category', 'idx_connectors_category');
        });

        DB::statement('CREATE INDEX idx_connectors_industries ON agent_connectors USING GIN (industries)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_connectors');
    }
};
