<?php

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
        Schema::create('runtime_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('runtime_tool_call_id');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('state')->default('pending');
            $table->foreignId('decision_by')->nullable()->constrained('users');
            $table->text('decision_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('runtime_tool_call_id')->references('id')->on('runtime_tool_calls')->cascadeOnDelete();

            $table->index('runtime_tool_call_id');
            $table->index(['state', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runtime_approvals');
    }
};
