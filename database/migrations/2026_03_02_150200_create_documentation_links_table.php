<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documentation_entry_id')->constrained('documentation_entries')->cascadeOnDelete();
            $table->foreignId('documentation_fragment_id')->nullable()->constrained('documentation_fragments')->nullOnDelete();
            $table->string('route_name')->nullable();
            $table->string('setting_key')->nullable();
            $table->string('feature_flag')->nullable();
            $table->timestamps();

            $table->index('route_name');
            $table->index('setting_key');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_links');
    }
};
