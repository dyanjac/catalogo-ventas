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

        DB::table('product_images')
            ->whereNull('product_sku')
            ->chunkById(200, function ($images): void {
                $skus = DB::table('products')
                    ->whereIn('id', $images->pluck('product_id')->filter()->all())
                    ->pluck('sku', 'id');

                foreach ($images as $image) {
                    $sku = $skus->get($image->product_id);

                    if ($sku !== null) {
                        DB::table('product_images')
                            ->where('id', $image->id)
                            ->update(['product_sku' => $sku]);
                    }
                }
            });
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
