<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_doc_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documentation_entry_id')->constrained('documentation_entries')->cascadeOnDelete();
            $table->enum('domain', ['api_doc'])->default('api_doc');
            $table->string('operation_id');
            $table->string('http_method', 16);
            $table->string('path');
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('section')->nullable();
            $table->json('tags')->nullable();
            $table->string('spec_version')->nullable();
            $table->string('spec_checksum', 64)->nullable();
            $table->json('linked_doc_slugs')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('operation_id');
            $table->index('domain');
            $table->index('section');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_doc_artifacts');
    }
};
