<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('product_sku', 80)->nullable()->after('product_id');
            $table->index('product_sku');
        });

        DB::statement('
            UPDATE product_images
            INNER JOIN products ON products.id = product_images.product_id
            SET product_images.product_sku = products.sku
            WHERE product_images.product_sku IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_product_sku_index');
            $table->dropColumn('product_sku');
        });
    }
};
