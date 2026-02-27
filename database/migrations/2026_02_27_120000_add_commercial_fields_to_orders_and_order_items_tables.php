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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('series', 4)->default('PED')->after('id');
            $table->unsignedBigInteger('order_number')->default(1)->after('series');
            $table->string('currency', 3)->default('PEN')->after('status');
            $table->decimal('discount', 10, 2)->default(0)->after('subtotal');
            $table->decimal('tax', 10, 2)->default(0)->after('shipping');
            $table->string('payment_method', 30)->default('cash')->after('shipping_address');
            $table->string('payment_status', 20)->default('pending')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->string('transaction_id')->nullable()->after('paid_at');
            $table->text('observations')->nullable()->after('transaction_id');

            $table->index(['series', 'order_number']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('currency', 3)->default('PEN')->after('product_id');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('unit_price');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['currency', 'discount_amount', 'tax_amount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_series_order_number_index');
            $table->dropColumn([
                'series',
                'order_number',
                'currency',
                'discount',
                'tax',
                'payment_method',
                'payment_status',
                'paid_at',
                'transaction_id',
                'observations',
            ]);
        });
    }
};
