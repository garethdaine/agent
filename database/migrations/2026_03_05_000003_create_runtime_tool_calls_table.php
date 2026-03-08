<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('runtime_tool_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('runtime_turn_id');
            $table->string('tool_name');
            $table->jsonb('arguments_json');
            $table->jsonb('result_json')->nullable();
            $table->string('status')->default('pending_approval');
            $table->integer('duration_ms')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('runtime_turn_id')->references('id')->on('runtime_turns')->cascadeOnDelete();

            $table->index('runtime_turn_id');
            $table->index(['status', 'requires_approval']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runtime_tool_calls');
    }
};
