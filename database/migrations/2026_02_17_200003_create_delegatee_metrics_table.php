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
        Schema::create('delegatee_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegatee_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('window_24h_json')->nullable();
            $table->json('window_7d_json')->nullable();
            $table->timestampTz('last_recomputed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegatee_metrics');
    }
};
