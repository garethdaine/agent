<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_agent_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('role_slug', 100);
            $table->text('role_description');
            $table->foreignId('delegatee_profile_id')
                ->constrained('delegatee_profiles')
                ->restrictOnDelete();
            $table->jsonb('capability_bindings');
            $table->jsonb('authority_overrides');
            $table->jsonb('default_output_schema')->nullable();
            $table->uuid('parent_agent_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('delegatee_profile_id');
            $table->index('archived_at');
        });

        // Self-referencing foreign key added separately after table exists
        Schema::table('org_agent_profiles', function (Blueprint $table) {
            $table->foreign('parent_agent_id')
                ->references('id')
                ->on('org_agent_profiles')
                ->nullOnDelete();
        });

        // Partial unique index: name unique per user for non-archived profiles
        DB::statement('
            CREATE UNIQUE INDEX org_agent_profiles_user_name_unique
            ON org_agent_profiles (user_id, name)
            WHERE archived_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('org_agent_profiles');
    }
};
