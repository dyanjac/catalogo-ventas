<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_document_response_histories')) {
            return;
        }

        Schema::create('billing_document_response_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
            $table->string('provider', 30)->nullable();
            $table->string('environment', 20)->nullable();
            $table->string('event', 30)->default('issue');
            $table->boolean('ok')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_class', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['billing_document_id', 'created_at']);
            $table->index(['provider', 'ok']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_response_histories');
    }
};
