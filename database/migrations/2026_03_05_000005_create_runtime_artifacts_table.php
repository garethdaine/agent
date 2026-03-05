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
        Schema::create('runtime_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('runtime_session_id');
            $table->string('type');
            $table->string('path');
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->foreign('runtime_session_id')->references('id')->on('runtime_sessions')->cascadeOnDelete();

            $table->index(['runtime_session_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runtime_artifacts');
    }
};
