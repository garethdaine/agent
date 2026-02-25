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
        Schema::create('chat_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('chat_message_id');
            $table->string('filename', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path', 1024);
            $table->string('provider_file_id', 255)->nullable();
            $table->string('scan_status', 16)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');

            $table->foreign('chat_message_id')
                ->references('id')
                ->on('chat_messages')
                ->cascadeOnDelete();

            $table->index(['scan_status']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
