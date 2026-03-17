<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_interrogation_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_interrogation_session_id', 'workflow_interrogation_attachments_session_fk')
                ->constrained('workflow_interrogation_sessions')
                ->cascadeOnDelete();
            $table->string('filename', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path', 1024);
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            $table->index(['workflow_interrogation_session_id', 'created_at'], 'workflow_interrogation_attachments_session_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_interrogation_attachments');
    }
};
