<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_branch_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('security_branches')->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
            $table->index(['branch_id', 'stock']);
        });

        $defaultBranchId = DB::table('security_branches')->where('is_default', true)->value('id');

        if ($defaultBranchId) {
            DB::table('products')->orderBy('id')->get(['id', 'stock', 'min_stock', 'created_at', 'updated_at'])->each(function (object $product) use ($defaultBranchId): void {
                DB::table('product_branch_stocks')->updateOrInsert(
                    [
                        'product_id' => $product->id,
                        'branch_id' => $defaultBranchId,
                    ],
                    [
                        'stock' => (int) ($product->stock ?? 0),
                        'min_stock' => (int) ($product->min_stock ?? 0),
                        'is_active' => true,
                        'created_at' => $product->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch_stocks');
    }
};
