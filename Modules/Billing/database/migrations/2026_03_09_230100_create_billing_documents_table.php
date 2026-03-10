<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('provider', 30);
            $table->string('document_type', 20);
            $table->string('series', 10);
            $table->string('number', 20);
            $table->date('issue_date');
            $table->string('customer_document_type', 5)->nullable();
            $table->string('customer_document_number', 20)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('currency', 3)->default('PEN');
            $table->string('status', 20)->default('draft');
            $table->string('sunat_ticket', 80)->nullable();
            $table->string('sunat_cdr_code', 20)->nullable();
            $table->text('sunat_cdr_description')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['issue_date']);
            $table->unique(['document_type', 'series', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_documents');
    }
};
