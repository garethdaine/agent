<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interrogation_tech_stacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interrogation_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('name', 120);
            $table->string('documentation_url', 2048);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['interrogation_session_id', 'name'], 'interr_tech_stacks_session_name_unique');
            $table->index(['interrogation_session_id', 'sequence'], 'interr_tech_stacks_session_sequence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interrogation_tech_stacks');
    }
};
