<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_council_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->jsonb('member_list'); // Array of {agent_id, perspective_label, weight?, is_chair?}
            $table->jsonb('evidence_payload_schema')->nullable();
            $table->jsonb('member_response_schema')->nullable();
            $table->string('synthesis_mode', 20)->default('majority'); // majority, weighted, chair_decides
            $table->boolean('use_model_synthesis')->default(false);
            $table->jsonb('report_sections')->nullable(); // agreements, conflicts, recommended actions
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_council_templates');
    }
};
