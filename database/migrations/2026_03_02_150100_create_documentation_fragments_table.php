<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_fragments', function (Blueprint $table): void {
            $table->id();
            $table->string('ui_key');
            $table->string('locale', 12)->default('en');
            $table->text('short_text');
            $table->text('long_text')->nullable();
            $table->foreignId('learn_more_entry_id')->nullable()->constrained('documentation_entries')->nullOnDelete();
            $table->enum('severity', ['info', 'warning', 'risk'])->default('info');
            $table->string('feature_flag')->nullable();
            $table->string('status')->default('published');
            $table->json('route_names')->nullable();
            $table->json('setting_keys')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['ui_key', 'locale'], 'documentation_fragments_ui_key_locale_unique');
            $table->index('updated_at');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_fragments');
    }
};
