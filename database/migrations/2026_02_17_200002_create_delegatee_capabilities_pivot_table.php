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
        Schema::create('delegatee_capabilities_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegatee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegation_capability_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['delegatee_profile_id', 'delegation_capability_id'],
                'delegatee_cap_pivot_profile_capability_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegatee_capabilities_pivot');
    }
};
