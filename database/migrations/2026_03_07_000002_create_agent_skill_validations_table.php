<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_skill_validations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('skill_name', 64);
            $table->unsignedBigInteger('team_id');
            $table->jsonb('validation_result');
            $table->decimal('risk_score', 4, 3);
            $table->boolean('overall_pass');
            $table->string('source', 20);
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['skill_name', 'created_at'], 'idx_skill_validations_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_skill_validations');
    }
};
