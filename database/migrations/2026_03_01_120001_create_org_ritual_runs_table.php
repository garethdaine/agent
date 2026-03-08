<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_ritual_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ritual_template_id')
                ->constrained('org_ritual_templates')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('state', 20)->default('draft');
            $table->unsignedBigInteger('delegation_graph_id')->nullable();
            $table->jsonb('phase_outputs')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->index('ritual_template_id');
            $table->index('user_id');
            $table->index('state');
            $table->index('correlation_id');

            $table->foreign('delegation_graph_id')
                ->references('id')
                ->on('delegation_graphs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_ritual_runs');
    }
};
