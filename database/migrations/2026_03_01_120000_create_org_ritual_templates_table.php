<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_ritual_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('cron_expression', 100);
            $table->string('timezone', 50)->default('UTC');
            $table->text('nl_source_metadata')->nullable();
            $table->jsonb('phase_graph');
            $table->jsonb('phase_role_mappings');
            $table->jsonb('context_inputs')->nullable();
            $table->jsonb('verification_strategy')->nullable();
            $table->jsonb('delivery_targets')->nullable();
            $table->integer('escalation_timeout_seconds')->default(3600);
            $table->string('notification_level', 20)->default('escalations_only');
            $table->boolean('is_paused')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['is_paused', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_ritual_templates');
    }
};
