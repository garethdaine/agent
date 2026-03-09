<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_score_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegatee_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 3, 2);
            $table->jsonb('components_json');
            $table->timestamp('calculated_at');
            $table->string('reason', 255);
            $table->timestamps();

            $table->index(['delegatee_profile_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_score_histories');
    }
};
