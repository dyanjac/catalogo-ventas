<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'organization_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_series_order_number_index');
            $table->unique(['organization_id', 'series', 'order_number'], 'orders_org_series_number_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'organization_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_org_series_number_unique');
            $table->index(['series', 'order_number']);
        });
    }
};
