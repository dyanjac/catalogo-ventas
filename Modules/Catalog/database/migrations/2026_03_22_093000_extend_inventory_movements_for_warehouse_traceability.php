<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('inventory_warehouses')->nullOnDelete();
            $table->decimal('average_cost_before', 14, 4)->default(0)->after('stock_after');
            $table->decimal('unit_cost', 14, 4)->default(0)->after('average_cost_before');
            $table->decimal('average_cost_after', 14, 4)->default(0)->after('unit_cost');
            $table->decimal('total_cost', 14, 4)->default(0)->after('average_cost_after');

            $table->index(['warehouse_id', 'product_id', 'created_at'], 'inventory_movements_warehouse_product_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_warehouse_product_created_idx');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn([
                'average_cost_before',
                'unit_cost',
                'average_cost_after',
                'total_cost',
            ]);
        });
    }
};
