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
        Schema::create('runtime_policy_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('runtime_session_id');
            $table->string('snapshot_reason');
            $table->jsonb('policy_json');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->foreign('runtime_session_id')->references('id')->on('runtime_sessions')->cascadeOnDelete();

            $table->index(['runtime_session_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runtime_policy_snapshots');
    }
};
