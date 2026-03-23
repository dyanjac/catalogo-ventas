<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('document_type', 20);
            $table->string('status', 20)->default('draft');
            $table->foreignId('branch_id')->constrained('security_branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->string('reason', 60)->nullable();
            $table->string('external_reference', 80)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'status', 'issued_at'], 'inventory_documents_type_status_issued_idx');
            $table->index(['branch_id', 'warehouse_id'], 'inventory_documents_branch_warehouse_idx');
        });

        Schema::create('inventory_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('inventory_documents')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('line_total', 14, 4)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'product_id'], 'inventory_document_items_document_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_document_items');
        Schema::dropIfExists('inventory_documents');
    }
};
