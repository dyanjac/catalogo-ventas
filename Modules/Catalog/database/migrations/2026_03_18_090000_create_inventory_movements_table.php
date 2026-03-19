<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('security_branches')->cascadeOnDelete();
            $table->string('movement_type', 40);
            $table->string('reason', 60)->nullable();
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_code', 80)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'product_id', 'created_at'], 'inventory_movements_branch_product_created_idx');
            $table->index(['reference_type', 'reference_id'], 'inventory_movements_reference_idx');
            $table->index(['movement_type', 'created_at'], 'inventory_movements_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
