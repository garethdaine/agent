<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_entries', function (Blueprint $table): void {
            $table->id();
            $table->enum('domain', ['product_doc', 'api_doc']);
            $table->string('slug');
            $table->string('locale', 12)->default('en');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('section')->nullable();
            $table->string('audience')->nullable();
            $table->string('status')->default('published');
            $table->string('version')->nullable();
            $table->json('tags')->nullable();
            $table->string('owner')->nullable();
            $table->longText('body_markdown');
            $table->longText('body_html')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->string('source_commit', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'slug', 'locale'], 'documentation_entries_domain_slug_locale_unique');
            $table->index('domain');
            $table->index('section');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_entries');
    }
};
