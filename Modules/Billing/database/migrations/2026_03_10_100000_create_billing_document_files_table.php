<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
            $table->string('file_type', 20); // xml|cdr
            $table->string('storage_disk', 30)->default('public');
            $table->string('storage_path');
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('hash_sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billing_document_id', 'file_type']);
            $table->unique(['billing_document_id', 'file_type', 'storage_path'], 'billing_doc_files_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_files');
    }
};
