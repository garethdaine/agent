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
        Schema::create('delegatee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('runner_type', 16);
            $table->string('command_template', 2000);
            $table->string('working_directory', 1024);
            $table->json('env_json')->nullable();
            $table->json('config_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('trust_score', 3, 2)->nullable();
            $table->timestamp('trust_updated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['user_id', 'runner_type', 'is_active', 'deleted_at'],
                'delegatee_profiles_user_runner_active_deleted_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegatee_profiles');
    }
};
